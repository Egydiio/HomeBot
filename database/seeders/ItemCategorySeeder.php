<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ── CASA (shared household expenses) ────────────────────────────
            // Grãos e básicos
            'arroz'           => 'house',
            'feijao'          => 'house',
            'feijão'          => 'house',
            'lentilha'        => 'house',
            'grao de bico'    => 'house',
            'ervilha'         => 'house',
            'macarrao'        => 'house',
            'macarrão'        => 'house',
            'espaguete'       => 'house',
            'farinha'         => 'house',
            'fuba'            => 'house',
            'fubá'            => 'house',
            'amido'           => 'house',
            'polvilho'        => 'house',
            'aveia'           => 'house',
            'granola'         => 'house',
            'cuscuz'          => 'house',
            'tapioca'         => 'house',

            // Temperos e condimentos
            'sal'             => 'house',
            'acucar'          => 'house',
            'açúcar'          => 'house',
            'oleo'            => 'house',
            'óleo'            => 'house',
            'azeite'          => 'house',
            'vinagre'         => 'house',
            'shoyu'           => 'house',
            'molho de tomate' => 'house',
            'extrato de tomate' => 'house',
            'ketchup'         => 'house',
            'mostarda'        => 'house',
            'maionese'        => 'house',
            'tempero'         => 'house',
            'alho'            => 'house',
            'cebola'          => 'house',
            'pimenta'         => 'house',

            // Laticínios e ovos
            'leite'           => 'house',
            'manteiga'        => 'house',
            'margarina'       => 'house',
            'queijo'          => 'house',
            'requeijao'       => 'house',
            'requeijão'       => 'house',
            'ovos'            => 'house',
            'ovo'             => 'house',
            'creme de leite'  => 'house',
            'leite condensado' => 'house',

            // Pães e derivados
            'pao'             => 'house',
            'pão'             => 'house',
            'torrada'         => 'house',
            'biscoito'        => 'house',
            'bolacha'         => 'house',

            // Frutas e vegetais (compartilhados)
            'banana'          => 'house',
            'maca'            => 'house',
            'maçã'            => 'house',
            'laranja'         => 'house',
            'tomate'          => 'house',
            'batata'          => 'house',
            'cenoura'         => 'house',
            'brocolis'        => 'house',
            'brócolos'        => 'house',
            'alface'          => 'house',
            'couve'           => 'house',
            'espinafre'       => 'house',
            'chuchu'          => 'house',
            'abobora'         => 'house',
            'abóbora'         => 'house',
            'inhame'          => 'house',
            'macaxeira'       => 'house',
            'mandioca'        => 'house',

            // Carnes básicas (shared)
            'frango'          => 'house',
            'carne'           => 'house',
            'patinho'         => 'house',
            'acém'            => 'house',
            'pernil'          => 'house',
            'linguica'        => 'house',
            'linguiça'        => 'house',
            'ovo'             => 'house',

            // Bebidas básicas
            'agua mineral'    => 'house',
            'agua'            => 'house',
            'café'            => 'house',
            'cafe'            => 'house',
            'cha'             => 'house',
            'chá'             => 'house',
            'leite de coco'   => 'house',

            // Limpeza — sempre casa
            'detergente'      => 'house',
            'sabao'           => 'house',
            'sabão'           => 'house',
            'sabao em po'     => 'house',
            'sabão em pó'     => 'house',
            'amaciante'       => 'house',
            'desinfetante'    => 'house',
            'multiuso'        => 'house',
            'limpador'        => 'house',
            'limpa vidro'     => 'house',
            'limpa forno'     => 'house',
            'remove manchas'  => 'house',
            'alvejante'       => 'house',
            'cloro'           => 'house',
            'agua sanitaria'  => 'house',
            'água sanitária'  => 'house',
            'esponja'         => 'house',
            'palha de aco'    => 'house',
            'palha de aço'    => 'house',
            'pano de prato'   => 'house',
            'pano multiuso'   => 'house',
            'vassoura'        => 'house',
            'rodo'            => 'house',
            'balde'           => 'house',
            'luva de borracha' => 'house',
            'saco de lixo'    => 'house',
            'lixeira'         => 'house',
            'papel toalha'    => 'house',
            'papel higienico' => 'house',
            'papel higiênico' => 'house',
            'lenco umedecido' => 'house',
            'lenço umedecido' => 'house',
            'guardanapo'      => 'house',
            'filme pvc'       => 'house',
            'papel aluminio'  => 'house',
            'papel alumínio'  => 'house',
            'isopor'          => 'house',
            'prendedor de roupa' => 'house',

            // Higiene coletiva
            'sabonete liquido' => 'house',
            'sabonete líquido' => 'house',
            'alcool gel'      => 'house',
            'álcool gel'      => 'house',
            'alcool'          => 'house',
            'repelente'       => 'house',

            // ── PESSOAL (personal, not shared) ──────────────────────────────
            // Bebidas alcoólicas
            'cerveja'         => 'personal',
            'vinho'           => 'personal',
            'vodka'           => 'personal',
            'whisky'          => 'personal',
            'cachaça'         => 'personal',
            'cachaca'         => 'personal',
            'rum'             => 'personal',
            'gin'             => 'personal',
            'tequila'         => 'personal',
            'energetico'      => 'personal',
            'energético'      => 'personal',
            'monster'         => 'personal',
            'red bull'        => 'personal',
            'negroni'         => 'personal',
            'espumante'       => 'personal',
            'champagne'       => 'personal',
            'chopp'           => 'personal',
            'skol'            => 'personal',
            'brahma'          => 'personal',
            'heineken'        => 'personal',
            'corona'          => 'personal',
            'budweiser'       => 'personal',
            'stella artois'   => 'personal',

            // Snacks e doces pessoais
            'chocolate'       => 'personal',
            'bala'            => 'personal',
            'chiclete'        => 'personal',
            'pirulito'        => 'personal',
            'bom bom'         => 'personal',
            'sorvete'         => 'personal',
            'picolé'          => 'personal',
            'picole'          => 'personal',
            'salgadinho'      => 'personal',
            'batata chips'    => 'personal',
            'cheetos'         => 'personal',
            'ruffles'         => 'personal',
            'doritos'         => 'personal',
            'amendoim'        => 'personal',
            'castanha'        => 'personal',
            'pipoca'          => 'personal',

            // Bebidas pessoais
            'refrigerante'    => 'personal',
            'coca cola'       => 'personal',
            'pepsi'           => 'personal',
            'guarana'         => 'personal',
            'guaraná'         => 'personal',
            'suco de caixinha' => 'personal',
            'nescau'          => 'personal',
            'toddy'           => 'personal',

            // Higiene pessoal
            'shampoo'         => 'personal',
            'condicionador'   => 'personal',
            'creme de cabelo' => 'personal',
            'hidratante'      => 'personal',
            'perfume'         => 'personal',
            'desodorante'     => 'personal',
            'sabonete'        => 'personal',
            'absorvente'      => 'personal',
            'fio dental'      => 'personal',
            'escova de dente' => 'personal',
            'creme dental'    => 'personal',
            'pasta de dente'  => 'personal',
            'lamina de barbear' => 'personal',
            'lâmina de barbear' => 'personal',
            'aparelho de barbear' => 'personal',
            'protetor solar'  => 'personal',
            'tinta de cabelo' => 'personal',
            'maquiagem'       => 'personal',
            'esmalte'         => 'personal',
            'locao'           => 'personal',
            'loção'           => 'personal',

            // Alimentação individual
            'iogurte'         => 'personal',
            'danone'          => 'personal',
            'yakult'          => 'personal',
            'actimel'         => 'personal',
        ];

        foreach ($items as $keyword => $category) {
            ItemCategory::updateOrCreate(
                ['keyword' => $keyword],
                ['category' => $category, 'source' => 'manual', 'confidence' => 100]
            );
        }
    }
}
