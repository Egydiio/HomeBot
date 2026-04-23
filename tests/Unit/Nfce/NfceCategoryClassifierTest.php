<?php

namespace Tests\Unit\Nfce;

use App\Services\Nfce\DTO\NfceItemDTO;
use App\Services\Nfce\NfceCategoryClassifier;
use Tests\TestCase;

class NfceCategoryClassifierTest extends TestCase
{
    private NfceCategoryClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new NfceCategoryClassifier();
    }

    public function test_classifies_milk_as_alimento(): void
    {
        $this->assertEquals('alimento', $this->classifier->detectCategory('Leite Integral UHT'));
    }

    public function test_classifies_rice_as_alimento(): void
    {
        $this->assertEquals('alimento', $this->classifier->detectCategory('Arroz Tipo 1 5Kg'));
    }

    public function test_classifies_chicken_as_alimento(): void
    {
        $this->assertEquals('alimento', $this->classifier->detectCategory('Frango Inteiro Resfriado'));
    }

    public function test_classifies_soda_as_bebida(): void
    {
        $this->assertEquals('bebida', $this->classifier->detectCategory('Refrigerante Cola 2L'));
    }

    public function test_classifies_water_as_bebida(): void
    {
        $this->assertEquals('bebida', $this->classifier->detectCategory('Água Mineral 500ml'));
    }

    public function test_classifies_detergent_as_limpeza(): void
    {
        $this->assertEquals('limpeza', $this->classifier->detectCategory('Detergente Neutro 500ml'));
    }

    public function test_classifies_bleach_as_limpeza(): void
    {
        $this->assertEquals('limpeza', $this->classifier->detectCategory('Água Sanitária 1L'));
    }

    public function test_classifies_shampoo_as_higiene(): void
    {
        $this->assertEquals('higiene', $this->classifier->detectCategory('Shampoo Anticaspa 400ml'));
    }

    public function test_classifies_soap_as_higiene(): void
    {
        $this->assertEquals('higiene', $this->classifier->detectCategory('Sabonete Dove 90g'));
    }

    public function test_classifies_unknown_as_outros(): void
    {
        $this->assertEquals('outros', $this->classifier->detectCategory('Produto Desconhecido XYZ'));
    }

    public function test_classification_is_case_insensitive(): void
    {
        $this->assertEquals('alimento', $this->classifier->detectCategory('LEITE INTEGRAL'));
    }

    public function test_classification_handles_accents(): void
    {
        $this->assertEquals('alimento', $this->classifier->detectCategory('Macarrão Espaguete'));
    }

    public function test_classifies_all_items_in_array(): void
    {
        $items = [
            new NfceItemDTO('Leite Integral', 1.0, 4.99, 4.99),
            new NfceItemDTO('Detergente Neutro', 1.0, 2.50, 2.50),
            new NfceItemDTO('Shampoo', 1.0, 12.90, 12.90),
        ];

        $result = $this->classifier->classifyAll($items);

        $this->assertEquals('alimento', $result[0]->category);
        $this->assertEquals('limpeza', $result[1]->category);
        $this->assertEquals('higiene', $result[2]->category);
    }

    public function test_with_category_creates_new_instance(): void
    {
        $item    = new NfceItemDTO('Produto', 1.0, 5.0, 5.0, 'outros');
        $updated = $item->withCategory('alimento');

        $this->assertEquals('outros', $item->category);
        $this->assertEquals('alimento', $updated->category);
    }
}
