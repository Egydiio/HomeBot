<?php

namespace Tests\Unit\Nfce;

use App\Services\Nfce\DTO\NfceItemDTO;
use App\Services\Nfce\NfceNormalizer;
use Tests\TestCase;

class NfceNormalizerTest extends TestCase
{
    private NfceNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new NfceNormalizer();
    }

    public function test_normalizes_name_to_title_case(): void
    {
        $result = $this->normalizer->normalizeName('LEITE INTEGRAL UHT');

        $this->assertEquals('Leite Integral Uht', $result);
    }

    public function test_collapses_extra_whitespace_in_name(): void
    {
        $result = $this->normalizer->normalizeName('  ARROZ   TIPO 1  ');

        $this->assertEquals('Arroz Tipo 1', $result);
    }

    public function test_normalizes_whole_quantity(): void
    {
        $result = $this->normalizer->normalizeQuantity(2.0000);

        $this->assertEquals(2.0, $result);
        $this->assertIsFloat($result);
    }

    public function test_keeps_fractional_quantity(): void
    {
        $result = $this->normalizer->normalizeQuantity(1.5);

        $this->assertEquals(1.5, $result);
    }

    public function test_parses_brazilian_money_simple(): void
    {
        $result = $this->normalizer->parseBrazilianMoney('3,99');

        $this->assertEquals(3.99, $result);
    }

    public function test_parses_brazilian_money_with_thousands(): void
    {
        $result = $this->normalizer->parseBrazilianMoney('1.234,56');

        $this->assertEquals(1234.56, $result);
    }

    public function test_parses_brazilian_money_with_rs_prefix(): void
    {
        $result = $this->normalizer->parseBrazilianMoney('R$ 45,90');

        $this->assertEquals(45.90, $result);
    }

    public function test_normalize_rounds_values_to_two_decimal_places(): void
    {
        $item = new NfceItemDTO('PRODUTO', 1.0, 3.999, 3.999);
        $normalized = $this->normalizer->normalize($item);

        $this->assertEquals(4.00, $normalized->unitValue);
        $this->assertEquals(4.00, $normalized->totalValue);
    }

    public function test_normalizes_all_items_in_array(): void
    {
        $items = [
            new NfceItemDTO('LEITE INTEGRAL', 2.0, 4.99, 9.98),
            new NfceItemDTO('ARROZ TIPO 1', 1.0, 8.50, 8.50),
        ];

        $result = $this->normalizer->normalizeAll($items);

        $this->assertCount(2, $result);
        $this->assertEquals('Leite Integral', $result[0]->name);
        $this->assertEquals('Arroz Tipo 1', $result[1]->name);
    }
}
