<?php

namespace Tests\Unit;

use App\Services\InvoiceAmountExtractor;
use PHPUnit\Framework\TestCase;

class InvoiceAmountExtractorTest extends TestCase
{
    public function test_detects_total_a_pagar_in_cop_format(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = <<<'TXT'
        FACTURA DE VENTA
        Subtotal 100.000
        IVA 19.000
        TOTAL A PAGAR $ 119.000
        TXT;

        $candidates = $extractor->findAmountCandidates($text);

        $this->assertSame(119000, $candidates[0]);
    }

    public function test_detects_valor_a_pagar(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = "Valor a pagar: 1.250.500\nNIT 900123456";

        $candidates = $extractor->findAmountCandidates($text);

        $this->assertSame(1250500, $candidates[0]);
    }

    public function test_prefers_total_over_nit_and_subtotal(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = <<<'TXT'
        NIT 901234567-8
        Factura No 4589123
        Producto A 45.000
        Subtotal 45.000
        IVA 8.550
        TOTAL A PAGAR $ 53.550
        TXT;

        $this->assertSame(53550, $extractor->findAmountCandidates($text)[0]);
    }

    public function test_prefers_total_on_next_line(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = <<<'TXT'
        Subtotal 80.000
        TOTAL A PAGAR
        $ 95.200
        TXT;

        $this->assertSame(95200, $extractor->findAmountCandidates($text)[0]);
    }

    public function test_ignores_large_id_like_numbers_without_separators(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = <<<'TXT'
        CUFE ABC123
        Referencia 901234567890
        TOTAL A PAGAR 12.500
        TXT;

        $this->assertSame(12500, $extractor->findAmountCandidates($text)[0]);
    }
}
