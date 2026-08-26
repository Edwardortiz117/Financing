<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceAmountExtractor
{
    /**
     * Extrae texto y el monto total sugerido desde una factura (imagen o PDF).
     *
     * @return array{amount: int|null, text: string, candidates: list<array{amount: int, label: string, score: int}>, method: string}
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
            'text' => Str::limit($text, 4000, ''),
            'candidates' => array_slice($ranked, 0, 5),
            'method' => $method,
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

        $result = Process::timeout(60)->run([
            'pdftotext',
            '-layout',
            '-enc',
            'UTF-8',
            $path,
            '-',
        ]);

        $text = trim($result->output());

        if ($text === '' || strlen(preg_replace('/\s+/', '', $text) ?? '') < 20) {
            $imagePath = $this->pdfFirstPageToImage($path);
            try {
                $text = $this->runTesseract($imagePath);
            } finally {
                @unlink($imagePath);
            }
        }

        return $text;
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
        // Mejora contraste/contraste para tickets y fotos; si falla, usa original
        $out = storage_path('framework/temp/ocr_'.uniqid('', true).'.png');
        $result = Process::timeout(60)->run([
            'convert',
            $imagePath,
            '-colorspace', 'Gray',
            '-resize', '200%',
            '-contrast-stretch', '2%x2%',
            '-sharpen', '0x1',
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
        $result = Process::timeout(90)->run([
            'pdftoppm',
            '-png',
            '-r',
            '300',
            '-f',
            '1',
            '-l',
            '1',
            '-singlefile',
            $pdfPath,
            $tmpBase,
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

        foreach ([6, 4, 3] as $psm) {
            $result = Process::timeout(90)->run([
                'tesseract',
                $imagePath,
                'stdout',
                '-l',
                'spa+eng',
                '--psm',
                (string) $psm,
            ]);

            $text = trim($result->output());
            if ($text !== '') {
                $outputs[] = $text;
            }
        }

        if ($outputs === []) {
            $result = Process::timeout(90)->run([
                'tesseract',
                $imagePath,
                'stdout',
                '-l',
                'eng',
                '--psm',
                '6',
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

        // Preferir el OCR que contenga etiquetas de total
        usort($outputs, function (string $a, string $b): int {
            return $this->totalLabelHits($b) <=> $this->totalLabelHits($a);
        });

        return $outputs[0];
    }

    private function totalLabelHits(string $text): int
    {
        return preg_match_all(
            '/total\s*a\s*pagar|valor\s*a\s*pagar|gran\s*total|importe\s*total|neto\s*a\s*pagar/iu',
            $text
        ) ?: 0;
    }

    /**
     * @return list<array{amount: int, label: string, score: int}>
     */
    public function rankAmountCandidates(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $lines = preg_split("/\n+/", $normalized) ?: [];
        $lineCount = max(count($lines), 1);
        $ranked = [];

        $strongLabels = [
            'total a pagar',
            'valor a pagar',
            'gran total',
            'total general',
            'importe total',
            'total factura',
            'monto total',
            'neto a pagar',
            'saldo a pagar',
            'total pagado',
            'total cobrado',
            'valor total',
            'total venta',
            'total documento',
        ];

        $weakPositive = [
            'total',
            'pago',
        ];

        $negativeLabels = [
            'subtotal',
            'sub total',
            'iva',
            'impuesto',
            'descuento',
            'base',
            'retefuente',
            'reteica',
            'reteiva',
            'propina',
            'cambio',
            'nit',
            'cufe',
            'factura',
            'resolucion',
            'telefono',
            'tel',
            'cel',
            'celular',
            'cantidad',
            'unidad',
            'precio unit',
            'unitario',
            'codigo',
            'c.c',
            'cedula',
        ];

        foreach ($lines as $index => $line) {
            $lineLower = mb_strtolower($line);
            $positionRatio = ($index + 1) / $lineCount; // totales suelen ir abajo

            if ($this->looksLikeIdOrMetaLine($lineLower)) {
                continue;
            }

            if (! preg_match_all(
                '/\$?\s*([0-9]{1,3}(?:[.\s][0-9]{3})+(?:[.,]\d{1,2})?|[0-9]{4,9}(?:[.,]\d{1,2})?)/u',
                $line,
                $matches,
                PREG_OFFSET_CAPTURE
            )) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $raw = $match[0];
                $offset = $match[1];
                $amount = $this->parseCopAmount($raw);
                if ($amount === null || $this->looksLikeIdNumber($amount, $raw, $lineLower)) {
                    continue;
                }

                $before = mb_strtolower(mb_substr($line, 0, $offset));
                $context = trim($before.' '.$lineLower);

                $label = 'monto';
                $score = 0;

                // Penalizar líneas claramente no-total
                foreach ($negativeLabels as $neg) {
                    if (str_contains($lineLower, $neg) && ! $this->lineHasStrongTotal($lineLower, $strongLabels)) {
                        $score -= 250;
                    }
                }

                foreach ($strongLabels as $strong) {
                    if (str_contains($lineLower, $strong) || str_contains($before, $strong)) {
                        $score += 1000;
                        $label = $strong;
                        break;
                    }
                }

                if ($label === 'monto') {
                    foreach ($weakPositive as $weak) {
                        // "total" solo cuenta si no es subtotal y está cerca del número
                        if ($weak === 'total' && str_contains($lineLower, 'subtotal')) {
                            continue;
                        }
                        if (preg_match('/\b'.preg_quote($weak, '/').'\b/u', $before)
                            || preg_match('/\b'.preg_quote($weak, '/').'\b/u', $lineLower)) {
                            $score += 200;
                            $label = $weak;
                            break;
                        }
                    }
                }

                // Preferir montos con separador de miles (más típicos en COP)
                if (preg_match('/\d[.\s]\d{3}/', $raw)) {
                    $score += 80;
                }

                // Preferir la zona inferior del documento
                if ($positionRatio >= 0.55) {
                    $score += (int) round(($positionRatio - 0.55) * 400);
                } else {
                    $score -= (int) round((0.55 - $positionRatio) * 120);
                }

                // Si hay $ cerca, suele ser dinero
                $window = mb_substr($line, max(0, $offset - 3), 12);
                if (str_contains($window, '$')) {
                    $score += 60;
                }

                // Preferir el último número de una línea de total (a menudo el valor)
                if ($label !== 'monto' && $this->isLastMoneyOnLine($line, $raw)) {
                    $score += 120;
                }

                // Evitar montos ridículamente altos frente a un total etiquetado menor
                if ($amount >= 50_000_000) {
                    $score -= 150;
                }

                $ranked[] = [
                    'amount' => $amount,
                    'label' => $label,
                    'score' => $score,
                    'line' => trim($line),
                ];
            }
        }

        // También buscar total en líneas adyacentes: "TOTAL A PAGAR" + monto en la siguiente
        for ($i = 0; $i < $lineCount - 1; $i++) {
            $current = mb_strtolower($lines[$i]);
            if (! $this->lineHasStrongTotal($current, $strongLabels)) {
                continue;
            }

            if (preg_match(
                '/\$?\s*([0-9]{1,3}(?:[.\s][0-9]{3})+(?:[.,]\d{1,2})?|[0-9]{4,9}(?:[.,]\d{1,2})?)/u',
                $lines[$i + 1],
                $m
            )) {
                $amount = $this->parseCopAmount($m[1]);
                if ($amount !== null && ! $this->looksLikeIdNumber($amount, $m[1], $current)) {
                    $ranked[] = [
                        'amount' => $amount,
                        'label' => 'total a pagar',
                        'score' => 1100,
                        'line' => trim($lines[$i].' '.$lines[$i + 1]),
                    ];
                }
            }
        }

        if ($ranked === []) {
            return [];
        }

        usort($ranked, function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            // Empate: preferir el que apareció más abajo (score ya incluye posición)
            return $b['amount'] <=> $a['amount'];
        });

        // Deduplicar por amount conservando el mejor score
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

        // Si el mejor no tiene etiqueta fuerte y hay uno etiquetado, preferir etiquetado
        $bestLabeled = null;
        foreach ($list as $item) {
            if ($item['score'] >= 900) {
                $bestLabeled = $item;
                break;
            }
        }
        if ($bestLabeled !== null && ($list[0]['score'] < 900 || $list[0]['label'] === 'monto')) {
            array_unshift($list, $bestLabeled);
            $seen = [];
            $list = array_values(array_filter($list, function ($item) use (&$seen) {
                $key = (string) $item['amount'];
                if (isset($seen[$key])) {
                    return false;
                }
                $seen[$key] = true;

                return true;
            }));
        }

        return $list;
    }

    /**
     * Compatibilidad con tests / consumidores antiguos.
     *
     * @return list<int>
     */
    public function findAmountCandidates(string $text): array
    {
        return array_map(
            fn (array $item) => $item['amount'],
            $this->rankAmountCandidates($text)
        );
    }

    private function lineHasStrongTotal(string $lineLower, array $strongLabels): bool
    {
        foreach ($strongLabels as $strong) {
            if (str_contains($lineLower, $strong)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeIdOrMetaLine(string $lineLower): bool
    {
        return (bool) preg_match(
            '/\b(cufe|nit\.?\s*:|c\.?\s*c\.?\s*:|autorizaci[oó]n|resoluci[oó]n|software|prefijo)\b/u',
            $lineLower
        );
    }

    private function looksLikeIdNumber(int $amount, string $raw, string $lineLower): bool
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        // NIT típico 9-10 dígitos sin separadores de miles
        if (strlen($digits) >= 9 && ! preg_match('/\d[.\s]\d{3}/', $raw)) {
            return true;
        }

        // Años / fechas
        if ($amount >= 1900 && $amount <= 2100) {
            return true;
        }

        if (preg_match('/\b(nit|cufe|c\.?\s*c\.?|cedula|factura\s*n)/u', $lineLower)) {
            return true;
        }

        return false;
    }

    private function isLastMoneyOnLine(string $line, string $raw): bool
    {
        if (! preg_match_all(
            '/\$?\s*([0-9]{1,3}(?:[.\s][0-9]{3})+(?:[.,]\d{1,2})?|[0-9]{4,9}(?:[.,]\d{1,2})?)/u',
            $line,
            $matches
        )) {
            return false;
        }

        $last = end($matches[1]);

        return $last === $raw;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Unificar espacios raros de OCR
        $text = str_replace(["\xc2\xa0", '•', '|'], ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        // Corregir OCR frecuente: T0TAL -> TOTAL
        $text = preg_replace('/\bt0tal\b/iu', 'TOTAL', $text) ?? $text;
        $text = preg_replace('/\bpag4r\b/iu', 'PAGAR', $text) ?? $text;

        return $text;
    }

    private function parseCopAmount(string $raw): ?int
    {
        $raw = trim(str_replace(["\xc2\xa0", ' '], '', $raw));

        // Formato CO/ES: 1.250.000,50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $raw)) {
            $clean = str_replace('.', '', $raw);
            $clean = preg_replace('/,\d{1,2}$/', '', $clean) ?? $clean;
            $value = (int) $clean;
        // Formato US: 1,250,000.50
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $raw)) {
            $clean = str_replace(',', '', $raw);
            $clean = preg_replace('/\.\d{1,2}$/', '', $clean) ?? $clean;
            $value = (int) $clean;
        } else {
            // 119000.00 o 119000
            if (preg_match('/^\d+[.,]\d{1,2}$/', $raw)) {
                $raw = preg_replace('/[.,]\d{1,2}$/', '', $raw) ?? $raw;
            }
            $value = (int) (preg_replace('/[^\d]/', '', $raw) ?? '0');
        }

        // Rangos típicos de factura personal en COP
        if ($value < 1000 || $value > 200_000_000) {
            return null;
        }

        return $value;
    }
}
