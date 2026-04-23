<?php

namespace App\Services\Nfce;

use App\Services\Nfce\DTO\NfceItemDTO;

class NfceCategoryClassifier
{
    // Categories are checked in order — limpeza before bebida avoids "água sanitária" → bebida
    private const KEYWORDS = [
        'alimento' => [
            'carne', 'frango', 'peixe', 'filé', 'file', 'bisteca', 'costela', 'alcatra',
            'leite', 'queijo', 'iogurte', 'manteiga', 'nata', 'creme de leite',
            'macarrao', 'macarrão', 'arroz', 'feijao', 'feijão', 'lentilha', 'grao', 'grão',
            'farinha', 'trigo', 'amido', 'amido de milho', 'fubá', 'fuba',
            'oleo', 'óleo', 'azeite', 'vinagre',
            'açúcar', 'acucar', 'sal', 'tempero', 'alho', 'cebola', 'molho',
            'pão', 'pao', 'bolo', 'biscoito', 'bolacha', 'wafer', 'torrada',
            'ovo', 'ovos', 'presunto', 'salsicha', 'linguiça', 'linguica', 'bacon',
            'tomate', 'alface', 'couve', 'cenoura', 'batata', 'chuchu', 'abobrinha',
            'fruta', 'banana', 'maçã', 'maca', 'laranja', 'uva', 'morango', 'mamão', 'mamao',
            'massa', 'sopa', 'caldo', 'extrato', 'achocolatado', 'café', 'cafe', 'chá', 'cha',
            'margarina', 'geleia', 'mel', 'granola', 'cereal', 'aveia',
            'atum', 'sardinha', 'maionese', 'catchup', 'ketchup', 'mostarda',
        ],
        'limpeza' => [
            'detergente', 'sabão em pó', 'sabao em po', 'amaciante', 'alvejante',
            'água sanitária', 'agua sanitaria', 'cloro', 'hipoclorito',
            'desinfetante', 'pinho sol', 'multiuso', 'limpa vidro',
            'esponja', 'flanela', 'pano de prato', 'vassoura', 'rodo',
            'saco de lixo', 'saco lixo', 'lixeira',
            'sabão de coco', 'sabao de coco',
        ],
        'bebida' => [
            'refrigerante', 'suco', 'néctar', 'nectar', 'soda', 'limonada',
            'água', 'agua', 'agua mineral', 'água mineral', 'água com gás', 'agua com gas',
            'energetico', 'energético', 'isotônico', 'isotonico',
            'cerveja', 'vinho', 'cachaça', 'cachaca', 'whisky', 'vodka', 'gin',
            'leite de coco', 'bebida láctea', 'bebida lactea',
        ],
        'higiene' => [
            'sabonete', 'shampoo', 'xampu', 'condicionador', 'creme de cabelo',
            'desodorante', 'antitranspirante', 'perfume', 'colônia', 'colonia',
            'creme dental', 'pasta de dente', 'enxaguante', 'fio dental',
            'absorvente', 'fralda', 'lenço umedecido', 'lenco umedecido',
            'papel higienico', 'papel higiênico', 'papel toalha',
            'hidratante', 'creme de mão', 'creme de mao', 'protetor solar',
            'cotonete', 'algodão', 'algodao', 'curativo', 'band-aid',
        ],
    ];

    /**
     * @param  NfceItemDTO[]  $items
     * @return NfceItemDTO[]
     */
    public function classifyAll(array $items): array
    {
        return array_map(fn(NfceItemDTO $item) => $this->classify($item), $items);
    }

    public function classify(NfceItemDTO $item): NfceItemDTO
    {
        $category = $this->detectCategory($item->name);

        return $item->withCategory($category);
    }

    public function detectCategory(string $name): string
    {
        $normalized = $this->normalize($name);

        foreach (self::KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $normalizedKeyword = $this->normalize($keyword);
                if (str_contains($normalized, $normalizedKeyword)) {
                    return $category;
                }
            }
        }

        return 'outros';
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        // Transliterate accents (é→e, ã→a, etc.) for fuzzy matching
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        // Keep only alphanumeric and spaces
        $value = preg_replace('/[^a-z0-9\s]/', '', $value);

        return preg_replace('/\s+/', ' ', trim($value));
    }
}
