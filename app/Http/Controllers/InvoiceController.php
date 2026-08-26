<?php

namespace App\Http\Controllers;

use App\Services\InvoiceAmountExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceAmountExtractor $extractor) {}

    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'invoice' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,pdf',
            ],
        ]);

        try {
            $result = $this->extractor->extract($request->file('invoice'));
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // Guarda copia temporal para depuración de lecturas fallidas
        try {
            $request->file('invoice')->storeAs(
                'invoice-debug',
                now()->format('Ymd_His').'_'.$request->file('invoice')->getClientOriginalName(),
                'local'
            );
        } catch (Throwable) {
            // ignore
        }

        $candidateAmounts = array_map(
            fn (array $item) => $item['amount'],
            $result['candidates']
        );

        if ($result['amount'] === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No se detectó un monto total. Puedes escribirlo manualmente o elegir un candidato.',
                'candidates' => $candidateAmounts,
                'candidate_details' => $result['candidates'],
                'method' => $result['method'],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'amount' => $result['amount'],
            'candidates' => $candidateAmounts,
            'candidate_details' => $result['candidates'],
            'method' => $result['method'],
            'debug_lines' => $result['debug_lines'] ?? [],
            'message' => 'Monto detectado: $ '.number_format($result['amount'], 0, ',', '.').'. Si no es correcto, elige otro candidato.',
        ]);
    }
}
