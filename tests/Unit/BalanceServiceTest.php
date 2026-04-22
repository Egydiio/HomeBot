<?php

namespace Tests\Unit;

use App\Models\Balance;
use App\Models\Group;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_balances_for_other_active_members(): void
    {
        [$group, $payer, $other] = $this->makeHousehold();

        $transaction = Transaction::create([
            'group_id' => $group->id,
            'member_id' => $payer->id,
            'type' => 'bill',
            'description' => 'Conta de luz',
            'total_amount' => 100,
            'house_amount' => 100,
            'status' => 'confirmed',
            'reference_month' => now()->startOfMonth(),
        ]);

        app(BalanceService::class)->updateBalance($transaction);

        $balance = Balance::first();

        $this->assertNotNull($balance);
        $this->assertSame($other->id, $balance->debtor_id);
        $this->assertSame($payer->id, $balance->creditor_id);
        $this->assertSame('50.00', $balance->amount);
    }

    public function test_it_offsets_inverse_balances_instead_of_duplicating_them(): void
    {
        [$group, $payer, $other] = $this->makeHousehold();

        $firstTransaction = Transaction::create([
            'group_id' => $group->id,
            'member_id' => $other->id,
            'type' => 'receipt',
            'description' => 'Mercado 1',
            'total_amount' => 40,
            'house_amount' => 40,
            'status' => 'confirmed',
            'reference_month' => now()->startOfMonth(),
        ]);

        app(BalanceService::class)->updateBalance($firstTransaction);

        $secondTransaction = Transaction::create([
            'group_id' => $group->id,
            'member_id' => $payer->id,
            'type' => 'receipt',
            'description' => 'Mercado 2',
            'total_amount' => 100,
            'house_amount' => 100,
            'status' => 'confirmed',
            'reference_month' => now()->startOfMonth(),
        ]);

        app(BalanceService::class)->updateBalance($secondTransaction);

        $balances = Balance::all();

        $this->assertCount(1, $balances);
        $this->assertSame($other->id, $balances->first()->debtor_id);
        $this->assertSame($payer->id, $balances->first()->creditor_id);
        $this->assertSame('30.00', $balances->first()->amount);
    }

    private function makeHousehold(): array
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
            'active' => true,
        ]);

        $payer = Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);

        $other = Member::create([
            'group_id' => $group->id,
            'name' => 'Irmã',
            'phone' => '5511888888888',
            'split_percent' => 50,
            'active' => true,
        ]);

        return [$group, $payer, $other];
    }
}
