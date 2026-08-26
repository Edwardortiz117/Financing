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

        $this->assertSame(119000, $extractor->findAmountCandidates($text)[0]);
    }

    public function test_detects_valor_a_pagar(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = "Valor a pagar: 1.250.500\nNIT 900123456";

        $this->assertSame(1250500, $extractor->findAmountCandidates($text)[0]);
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

    public function test_prefers_ticket_total_over_huge_dian_amounts(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = <<<'TXT'
        FACTURA DE VENTA FESS33661586
        NIT 900777063
        Valor bruto acumulado 9.188.353
        Base 700.946
        IVA 46.552
        Retención 3.130
        TOTAL A PAGAR $ 59.900
        TXT;

        $candidates = $extractor->findAmountCandidates($text);

        $this->assertSame(59900, $candidates[0]);
        $this->assertNotSame(9188353, $candidates[0]);
    }

    public function test_detects_us_format_total_from_dian_invoice(): void
    {
        $extractor = new InvoiceAmountExtractor;
        $text = <<<'TXT'
        Factura Electrónica de Venta No.FESS33661586
        SPORTY CITY S.A.S. - Nit. 900777063-3
        1 MENSUALIDAD SMART 1.00 59,900.00 0.00 59,900.00
        Total Valor: 59,900.00 0.00 59,900.00
        Total a Pagar: 59,900.00
        RESOLUCION DIAN No.18764097798784 de 2025-08-29
        CUFE: b5edde87de11fd2ddc174f2f1a61f987925933cdb46552bcf3130b647c47a9ba0bf9188353c892d2c16dca4c2e700946
        TXT;

        $candidates = $extractor->findAmountCandidates($text);

        $this->assertSame(59900, $candidates[0]);
        $this->assertNotContains(9188353, $candidates);
        $this->assertNotContains(187640977, $candidates);
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
