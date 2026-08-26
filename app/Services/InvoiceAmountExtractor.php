<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceAmountExtractor
{
    /**
     * @return array{amount: int|null, text: string, candidates: list<array{amount: int, label: string, score: int}>, method: string, debug_lines: list<string>}
     */
    public function extract(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?: '';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if ($this->isPdf($mime, $extension)) {
            $text = $this->extractTextFromPdf($file);
            $method = 'pdf';
        } elseif ($this->isImage($mime, $extension)) {
            $text = $this->extractTextFromImage($file);
            $method = 'ocr';
        } else {
            throw new RuntimeException('Formato no soportado. Usa JPG, PNG, WEBP o PDF.');
        }

        $ranked = $this->rankAmountCandidates($text);
        $amount = $ranked[0]['amount'] ?? null;

        return [
            'amount' => $amount,
            'text' => Str::limit($text, 5000, ''),
            'candidates' => array_slice($ranked, 0, 6),
            'method' => $method,
            'debug_lines' => $this->debugTotalLines($text),
        ];
    }

    private function isPdf(string $mime, string $extension): bool
    {
        return $mime === 'application/pdf' || $extension === 'pdf';
    }

    private function isImage(string $mime, string $extension): bool
    {
        return str_starts_with($mime, 'image/')
            || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'], true);
    }

    private function extractTextFromPdf(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! $path) {
            throw new RuntimeException('No se pudo leer el archivo PDF.');
        }

        $layout = Process::timeout(60)->run([
            'pdftotext', '-layout', '-enc', 'UTF-8', $path, '-',
        ])->output();

        $raw = Process::timeout(60)->run([
            'pdftotext', '-raw', '-enc', 'UTF-8', $path, '-',
        ])->output();

        $text = trim($layout."\n".$raw);

        // Facturas DIAN / escaneadas: complementar siempre con OCR de la 1ª página
        // si no hay total claro o solo aparecen montos enormes.
        $needsOcr = $this->shouldOcrPdf($text);
        if ($needsOcr || strlen(preg_replace('/\s+/', '', $text) ?? '') < 40) {
            $imagePath = $this->pdfFirstPageToImage($path);
            try {
                $ocr = $this->runTesseract($imagePath);
                $text = trim($text."\n".$ocr);
            } finally {
                @unlink($imagePath);
            }
        }

        return $text;
    }

    private function shouldOcrPdf(string $text): bool
    {
        $ranked = $this->rankAmountCandidates($text);
        if ($ranked === []) {
            return true;
        }

        $best = $ranked[0];
        // Si el "mejor" es un monto enorme sin etiqueta fuerte de total a pagar, OCR
        if ($best['amount'] >= 1_000_000 && $best['score'] < 1500) {
            return true;
        }

        if (! preg_match('/total\s*a\s*pagar|valor\s*a\s*pagar|gran\s*total/iu', $text)) {
            return true;
        }

        return false;
    }

    private function extractTextFromImage(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! $path) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        $prepared = $this->preprocessImage($path);

        try {
            return $this->runTesseract($prepared);
        } finally {
            if ($prepared !== $path) {
                @unlink($prepared);
            }
        }
    }

    private function preprocessImage(string $imagePath): string
    {
        $out = storage_path('framework/temp/ocr_'.uniqid('', true).'.png');
        $result = Process::timeout(60)->run([
            'convert',
            $imagePath,
            '-colorspace', 'Gray',
            '-resize', '220%',
            '-contrast-stretch', '1%x1%',
            '-sharpen', '0x1.2',
            $out,
        ]);

        if ($result->successful() && is_file($out)) {
            return $out;
        }

        @unlink($out);

        return $imagePath;
    }

    private function pdfFirstPageToImage(string $pdfPath): string
    {
        $tmpBase = storage_path('framework/temp/invoice_'.uniqid('', true));
        $result = Process::timeout(120)->run([
            'pdftoppm', '-png', '-r', '300', '-f', '1', '-l', '1', '-singlefile', $pdfPath, $tmpBase,
        ]);

        $imagePath = $tmpBase.'.png';
        if (! is_file($imagePath)) {
            throw new RuntimeException(
                'No se pudo convertir el PDF para OCR. '.trim($result->errorOutput() ?: $result->output())
            );
        }

        return $imagePath;
    }

    private function runTesseract(string $imagePath): string
    {
        $outputs = [];

        foreach ([6, 4, 11] as $psm) {
            $result = Process::timeout(90)->run([
                'tesseract', $imagePath, 'stdout', '-l', 'spa+eng', '--psm', (string) $psm,
            ]);
            $text = trim($result->output());
            if ($text !== '') {
                $outputs[] = $text;
            }
        }

        if ($outputs === []) {
            $result = Process::timeout(90)->run([
                'tesseract', $imagePath, 'stdout', '-l', 'eng', '--psm', '6',
            ]);
            $text = trim($result->output());
            if ($text === '') {
                throw new RuntimeException(
                    'No se pudo leer texto de la factura. Prueba con una foto más nítida. '
                    .trim($result->errorOutput())
                );
            }

            return $text;
        }

        usort($outputs, fn (string $a, string $b) => $this->totalLabelHits($b) <=> $this->totalLabelHits($a));

        return implode("\n", $outputs);
    }

    private function totalLabelHits(string $text): int
    {
        return preg_match_all(
            '/total\s*a\s*pagar|valor\s*a\s*pagar|gran\s*total|importe\s*total|neto\s*a\s*pagar|\btotal\b/iu',
            $text
        ) ?: 0;
    }

    /**
     * @return list<array{amount: int, label: string, score: int}>
     */
    public function rankAmountCandidates(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $ranked = [];

        // 1) Prioridad máxima: monto inmediatamente después de etiqueta fuerte
        foreach ($this->extractLabeledTotals($normalized) as $item) {
            $ranked[] = $item;
        }

        // 2) Recorrido por líneas (refuerzo / fallback)
        foreach ($this->extractLineCandidates($normalized) as $item) {
            $ranked[] = $item;
        }

        if ($ranked === []) {
            return [];
        }

        $ranked = $this->applyOutlierPenalties($ranked);

        usort($ranked, function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            // Empate: preferir montos más "típicos" de compra personal (< 5M)
            $aTypical = $a['amount'] <= 5_000_000 ? 1 : 0;
            $bTypical = $b['amount'] <= 5_000_000 ? 1 : 0;
            if ($aTypical !== $bTypical) {
                return $bTypical <=> $aTypical;
            }

            return $a['amount'] <=> $b['amount'];
        });

        $unique = [];
        foreach ($ranked as $item) {
            $key = (string) $item['amount'];
            if (! isset($unique[$key]) || $item['score'] > $unique[$key]['score']) {
                $unique[$key] = [
                    'amount' => $item['amount'],
                    'label' => $item['label'],
                    'score' => $item['score'],
                ];
            }
        }

        $list = array_values($unique);
        usort($list, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $list;
    }

    /**
     * @return list<int>
     */
    public function findAmountCandidates(string $text): array
    {
        return array_map(
            fn (array $item) => $item['amount'],
            $this->rankAmountCandidates($text)
        );
    }

    private function moneyRegex(bool $withLeadingDollarGroup = false): string
    {
        // Soporta:
        // - CO: 59.900 | 1.250.000,50
        // - US: 59,900.00 | 1,250,000.50
        // - plano: 59900 | 59900.00
        $amount = '(?:'
            .'[0-9]{1,3}(?:\.[0-9]{3})+(?:,[0-9]{1,2})?'
            .'|'
            .'[0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?'
            .'|'
            .'[0-9]{4,9}(?:[.,][0-9]{1,2})?'
            .')';

        if ($withLeadingDollarGroup) {
            return '(\$?\s*)('.$amount.')';
        }

        return $amount;
    }

    /**
     * Extrae totales anclados a etiquetas (el número MÁS CERCANO después de la etiqueta).
     *
     * @return list<array{amount: int, label: string, score: int, line: string}>
     */
    private function extractLabeledTotals(string $text): array
    {
        $labels = [
            'total a pagar' => 2500,
            'valor a pagar' => 2500,
            'neto a pagar' => 2400,
            'saldo a pagar' => 2400,
            'gran total' => 2300,
            'total general' => 2200,
            'importe total' => 2200,
            'total factura' => 2200,
            'total documento' => 2100,
            'total venta' => 2000,
            'total valor' => 2000,
            'monto total' => 2000,
            'total pagado' => 2000,
            'valor total' => 1600,
            'total' => 1200,
        ];

        $money = $this->moneyRegex(true);
        $found = [];

        foreach ($labels as $label => $baseScore) {
            $pattern = '/('.preg_replace('/\s+/', '\\s*', preg_quote($label, '/')).')[^\n\d$]{0,40}'.$money.'/iu';
            if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $full = $match[0];
                $raw = $match[count($match) - 1];
                $amount = $this->parseCopAmount($raw);
                if ($amount === null) {
                    continue;
                }

                $around = mb_strtolower($full);
                if ($this->isNegativeContext($around, $label)) {
                    continue;
                }

                $score = $baseScore;

                // Bonus si el monto está muy cerca de la etiqueta
                $score += 200;

                if (str_contains($full, '$')) {
                    $score += 80;
                }

                // Penalizar totales enormes (suelen ser acumulados / bases DIAN)
                if ($amount >= 2_000_000) {
                    $score -= 900;
                } elseif ($amount >= 500_000) {
                    $score -= 250;
                }

                // Favorecer rangos típicos de ticket/factura personal
                if ($amount >= 5_000 && $amount <= 500_000) {
                    $score += 350;
                }

                $found[] = [
                    'amount' => $amount,
                    'label' => $label,
                    'score' => $score,
                    'line' => trim(preg_replace('/\s+/', ' ', $full) ?? $full),
                ];
            }
        }

        // Etiqueta en una línea y monto en la siguiente
        $lines = preg_split("/\n+/", $text) ?: [];
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $current = mb_strtolower($lines[$i]);
            $next = mb_strtolower($lines[$i + 1]);

            // No tomar resolución DIAN / CUFE / hashes como "siguiente línea"
            if ($this->looksLikeIdOrMetaLine($next) || preg_match('/resoluci|dian|cufe|prefijo|habilita/u', $next)) {
                continue;
            }

            foreach ($labels as $label => $baseScore) {
                if (! str_contains($current, $label)) {
                    continue;
                }
                if ($this->isNegativeContext($current, $label)) {
                    continue;
                }
                if (! preg_match('/'.$this->moneyRegex().'/u', $lines[$i + 1], $m)) {
                    continue;
                }
                $amount = $this->parseCopAmount($m[0]);
                if ($amount === null) {
                    continue;
                }

                $score = $baseScore + 180;
                if ($amount >= 5_000 && $amount <= 500_000) {
                    $score += 350;
                }
                if ($amount >= 2_000_000) {
                    $score -= 900;
                }

                $found[] = [
                    'amount' => $amount,
                    'label' => $label,
                    'score' => $score,
                    'line' => trim($lines[$i].' '.$lines[$i + 1]),
                ];
            }
        }

        return $found;
    }

    /**
     * @return list<array{amount: int, label: string, score: int, line: string}>
     */
    private function extractLineCandidates(string $text): array
    {
        $lines = preg_split("/\n+/", $text) ?: [];
        $lineCount = max(count($lines), 1);
        $ranked = [];

        foreach ($lines as $index => $line) {
            $lineLower = mb_strtolower($line);
            $positionRatio = ($index + 1) / $lineCount;

            if ($this->looksLikeIdOrMetaLine($lineLower) || preg_match('/\b(cufe|resoluci|dian no\.?)/u', $lineLower)) {
                continue;
            }

            // Evitar números embebidos en hashes CUFE (hex largo)
            if (preg_match('/[a-f0-9]{32,}/i', $line)) {
                continue;
            }

            if (! preg_match_all(
                '/\$?\s*('.$this->moneyRegex().')/u',
                $line,
                $matches,
                PREG_OFFSET_CAPTURE
            )) {
                continue;
            }

            foreach ($matches[1] as $matchIndex => $match) {
                $raw = $match[0];
                $offset = $match[1];
                $amount = $this->parseCopAmount($raw);
                if ($amount === null || $this->looksLikeIdNumber($amount, $raw, $lineLower)) {
                    continue;
                }

                if ($this->isNegativeContext($lineLower, '')) {
                    // Permitir si hay total a pagar explícito
                    if (! str_contains($lineLower, 'total a pagar') && ! str_contains($lineLower, 'valor a pagar')) {
                        continue;
                    }
                }

                $score = 40;
                $label = 'monto';

        // Preferir montos con separador de miles (más típicos en COP/US money)
                if (preg_match('/\d[.,\s]\d{3}/', $raw)) {
                    $score += 50;
                }

                if ($positionRatio >= 0.6) {
                    $score += 80;
                }

                $window = mb_substr($line, max(0, $offset - 3), 14);
                if (str_contains($window, '$')) {
                    $score += 40;
                }

                // En líneas de total, preferir el PRIMER monto tras la etiqueta (no el último)
                if (preg_match('/total\s*a\s*pagar|valor\s*a\s*pagar|gran\s*total/iu', $lineLower)) {
                    $score += 400;
                    $label = 'total';
                    if ($matchIndex === 0) {
                        $score += 300;
                    } else {
                        $score -= 200 * $matchIndex;
                    }
                }

                if ($amount >= 2_000_000) {
                    $score -= 600;
                }

                if ($amount >= 5_000 && $amount <= 500_000) {
                    $score += 120;
                }

                $ranked[] = [
                    'amount' => $amount,
                    'label' => $label,
                    'score' => $score,
                    'line' => trim($line),
                ];
            }
        }

        return $ranked;
    }

    /**
     * @param  list<array{amount: int, label: string, score: int}>  $ranked
     * @return list<array{amount: int, label: string, score: int}>
     */
    private function applyOutlierPenalties(array $ranked): array
    {
        $amounts = array_values(array_unique(array_map(fn ($i) => $i['amount'], $ranked)));
        sort($amounts);

        if (count($amounts) < 2) {
            return $ranked;
        }

        $median = $amounts[(int) floor((count($amounts) - 1) / 2)];

        foreach ($ranked as &$item) {
            // Si un monto es >15x la mediana de candidatos, casi seguro no es el total del ticket
            if ($median > 0 && $item['amount'] > max($median * 15, 1_000_000)) {
                $item['score'] -= 1200;
            }
        }
        unset($item);

        return $ranked;
    }

    private function isNegativeContext(string $lineLower, string $matchedLabel): bool
    {
        $negatives = [
            'subtotal', 'sub total', 'iva', 'impuesto', 'descuento', 'base gravable',
            'retefuente', 'reteica', 'reteiva', 'propina', 'cambio', 'anticipo',
            'nit', 'cufe', 'resolucion', 'resoluci', 'telefono', 'cantidad', 'unitario',
            'precio unit', 'codigo', 'cedula', 'c.c', 'valor bruto acumulado',
            'saldo anterior', 'cupo', 'consumo acumulado', 'dian no',
        ];

        foreach ($negatives as $neg) {
            if ($matchedLabel !== '' && str_contains($matchedLabel, $neg)) {
                continue;
            }
            if (str_contains($lineLower, $neg)) {
                // "total a pagar" puede compartir línea con IVA a veces; no descartar totales fuertes
                if (str_contains($lineLower, 'total a pagar') || str_contains($lineLower, 'valor a pagar')) {
                    if (in_array($neg, ['iva', 'impuesto', 'descuento'], true)) {
                        continue;
                    }
                }

                return true;
            }
        }

        return false;
    }

    private function looksLikeIdOrMetaLine(string $lineLower): bool
    {
        return (bool) preg_match(
            '/\b(cufe|nit\.?\s*:|c\.?\s*c\.?\s*:|autorizaci[oó]n|resoluci[oó]n|software|prefijo|folio)\b/u',
            $lineLower
        );
    }

    private function looksLikeIdNumber(int $amount, string $raw, string $lineLower): bool
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) >= 9 && ! preg_match('/\d[.,\s]\d{3}/', $raw)) {
            return true;
        }

        if ($amount >= 1900 && $amount <= 2100) {
            return true;
        }

        if (preg_match('/\b(nit|cufe|c\.?\s*c\.?|cedula|factura\s*n|fess)/u', $lineLower)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function debugTotalLines(string $text): array
    {
        $lines = preg_split("/\n+/", $this->normalizeText($text)) ?: [];
        $hits = [];
        foreach ($lines as $line) {
            if (preg_match('/total|pagar|valor|\$/iu', $line)) {
                $hits[] = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
            }
            if (count($hits) >= 12) {
                break;
            }
        }

        return $hits;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(
            ["\xc2\xa0", '•', '|', '·', '’', "'", '`', '´'],
            [' ', ' ', ' ', '.', '.', '.', '.', '.'],
            $text
        );
        // Separadores de miles unicode raros → punto
        $text = str_replace(["\xe2\x80\xa4", "\xca\x99"], '.', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\bt0tal\b/iu', 'TOTAL', $text) ?? $text;
        $text = preg_replace('/\bpag4r\b/iu', 'PAGAR', $text) ?? $text;
        $text = preg_replace('/\bto\s*tal\b/iu', 'TOTAL', $text) ?? $text;

        return $text;
    }

    private function parseCopAmount(string $raw): ?int
    {
        $raw = trim(str_replace(["\xc2\xa0", ' ', '$'], '', $raw));

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $raw)) {
            $clean = str_replace('.', '', $raw);
            $clean = preg_replace('/,\d{1,2}$/', '', $clean) ?? $clean;
            $value = (int) $clean;
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $raw)) {
            $clean = str_replace(',', '', $raw);
            $clean = preg_replace('/\.\d{1,2}$/', '', $clean) ?? $clean;
            $value = (int) $clean;
        } else {
            if (preg_match('/^\d+[.,]\d{1,2}$/', $raw)) {
                $raw = preg_replace('/[.,]\d{1,2}$/', '', $raw) ?? $raw;
            }
            $value = (int) (preg_replace('/[^\d]/', '', $raw) ?? '0');
        }

        if ($value < 1000 || $value > 200_000_000) {
            return null;
        }

        return $value;
    }
}
