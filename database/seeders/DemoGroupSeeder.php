<?php

namespace Database\Seeders;

use App\Models\Balance;
use App\Models\Group;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Limpa os dados anteriores
        Balance::truncate();
        TransactionItem::truncate();
        Transaction::truncate();
        Member::truncate();
        Group::truncate();

        // Cria o grupo
        $group = Group::create([
            'name'   => 'Nossa Casa',
            'slug'   => Str::slug('nossa-casa-' . uniqid()),
            'active' => true,
        ]);

        // Cria os membros — substitua pelos números reais
        $voce = Member::create([
            'group_id'      => $group->id,
            'name'          => 'Você',
            'phone'         => '5531981114334', // ← seu número aqui
            'pix_key'       => 'egydiio@hotmail.com',
            'split_percent' => 50,
            'active'        => true,
        ]);

        $irma = Member::create([
            'group_id'      => $group->id,
            'name'          => 'Irmã',
            'phone'         => '5531987732546', // ← número da sua irmã aqui
            'pix_key'       => 'irma@gmail.com',
            'split_percent' => 50,
            'active'        => true,
        ]);

        $this->command->info("Grupo criado: {$group->name}");
        $this->command->info("Membro 1: {$voce->name} — {$voce->phone}");
        $this->command->info("Membro 2: {$irma->name} — {$irma->phone}");

        // Cria transações de exemplo para simular o mês
        $this->createSampleTransactions($group, $voce, $irma);
    }

    private function createSampleTransactions(Group $group, Member $voce, Member $irma): void
    {
        $month = now()->startOfMonth();

        // Transação 1 — você foi ao mercado
        $t1 = Transaction::create([
            'group_id'        => $group->id,
            'member_id'       => $voce->id,
            'type'            => 'receipt',
            'description'     => 'Mercado Extra',
            'total_amount'    => 223.23,
            'house_amount'    => 215.25, // descontou o energético
            'receipt_image'   => null,
            'status'          => 'confirmed',
            'reference_month' => $month,
        ]);

        TransactionItem::insert([
            ['transaction_id' => $t1->id, 'name' => 'Leite Camponesa 1L',    'value' => 7.98,  'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Energético Monster',    'value' => 15.96, 'category' => 'personal', 'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Acém Resf kg',          'value' => 64.04, 'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Chã de Dentro Resf kg', 'value' => 16.68, 'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Espaguete Vilma',       'value' => 4.49,  'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Filé Peito Frango',     'value' => 62.70, 'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Maionese Hellmanns',    'value' => 13.99, 'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Penne Vilma',           'value' => 4.49,  'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['transaction_id' => $t1->id, 'name' => 'Sorvete Variatta 1.8L', 'value' => 32.90, 'category' => 'house',    'confirmed' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Transação 2 — sua irmã pagou a conta de luz
        $t2 = Transaction::create([
            'group_id'        => $group->id,
            'member_id'       => $irma->id,
            'type'            => 'bill',
            'description'     => 'Conta de luz',
            'total_amount'    => 180.00,
            'house_amount'    => 180.00,
            'receipt_image'   => null,
            'status'          => 'confirmed',
            'reference_month' => $month,
        ]);

        // Calcula os saldos das transações de exemplo
        $this->calculateSampleBalances($group, $voce, $irma, $month);

        $this->command->info("Transações de exemplo criadas!");
    }

    private function calculateSampleBalances(
        Group  $group,
        Member $voce,
        Member $irma,
               $month
    ): void {
        $referenceMonth = $month->format('Y-m-01');

        // T1: você pagou R$ 215.25 de casa → irmã te deve R$ 107.62
        // T2: irmã pagou R$ 180.00 de conta → você deve R$ 90.00 pra ela
        // Saldo líquido: irmã te deve 107.62 - 90.00 = R$ 17.62

        Balance::create([
            'group_id'        => $group->id,
            'debtor_id'       => $irma->id,
            'creditor_id'     => $voce->id,
            'amount'          => 17.62,
            'reference_month' => $referenceMonth,
        ]);

        $this->command->info("Saldo calculado: {$irma->name} deve R$ 17,62 para {$voce->name}");
    }
}
