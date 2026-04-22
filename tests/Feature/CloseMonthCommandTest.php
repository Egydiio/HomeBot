<?php

namespace Tests\Feature;

use App\Jobs\SendPixCharge;
use App\Models\Balance;
use App\Services\BalanceService;
use App\Services\BusinessDayService;
use App\Models\Group;
use App\Models\Member;
use App\Models\MonthlyClose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CloseMonthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_option_creates_monthly_close_and_dispatches_charge_job(): void
    {
        Queue::fake();

        [$group, $debtor, $creditor] = $this->makeHousehold();

        $businessDays = Mockery::mock(BusinessDayService::class);
        $businessDays->shouldReceive('getReferenceMonth')
            ->once()
            ->andReturn('2026-03-01');
        $businessDays->shouldNotReceive('isFifthBusinessDayToday');
        $this->app->instance(BusinessDayService::class, $businessDays);

        $debt = Balance::make([
            'group_id' => $group->id,
            'debtor_id' => $debtor->id,
            'creditor_id' => $creditor->id,
            'amount' => 42.35,
            'reference_month' => '2026-03-01',
        ]);
        $debt->setRelation('creditor', $creditor);

        $balanceService = Mockery::mock(BalanceService::class);
        $balanceService->shouldReceive('getMemberSummary')
            ->once()
            ->with(Mockery::on(fn (Member $member) => $member->is($creditor)), '2026-03-01')
            ->andReturn([
                'debts' => collect(),
                'credits' => collect(),
                'total_debt' => 0,
                'total_credit' => 0,
                'net' => 0,
            ]);
        $balanceService->shouldReceive('getMemberSummary')
            ->once()
            ->with(Mockery::on(fn (Member $member) => $member->is($debtor)), '2026-03-01')
            ->andReturn([
                'debts' => collect([$debt]),
                'credits' => collect(),
                'total_debt' => 42.35,
                'total_credit' => 0,
                'net' => -42.35,
            ]);
        $this->app->instance(BalanceService::class, $balanceService);

        $this->artisan('homebot:close-month', ['--force' => true])
            ->assertSuccessful();

        $close = MonthlyClose::first();

        $this->assertNotNull($close);
        $this->assertSame($group->id, $close->group_id);
        $this->assertSame($debtor->id, $close->debtor_id);
        $this->assertSame($creditor->id, $close->creditor_id);
        $this->assertSame('pending', $close->status);
        $this->assertSame('42.35', $close->amount);

        Queue::assertPushed(SendPixCharge::class, fn (SendPixCharge $job) => $job->monthlyCloseId === $close->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeHousehold(): array
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
            'active' => true,
        ]);

        $creditor = Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);

        $debtor = Member::create([
            'group_id' => $group->id,
            'name' => 'Irmã',
            'phone' => '5511888888888',
            'split_percent' => 50,
            'active' => true,
        ]);

        return [$group, $debtor, $creditor];
    }
}
