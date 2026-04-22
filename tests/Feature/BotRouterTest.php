<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\BalanceService;
use App\Services\Bot\BotRouter;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\BillHandler;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\Bot\Handlers\HelpHandler;
use App\Services\Bot\Handlers\ReceiptHandler;
use App\Services\RuleBasedClassifierService;
use App\Services\ZApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BotRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_bill_confirmation_uses_detected_total_and_confirms_transaction(): void
    {
        [$member, $transaction] = $this->makeBillTransaction();

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('getState')->once()->with($member->phone)->andReturn(ConversationState::STATE_WAITING_CONFIRMATION);
        $state->shouldReceive('getData')->once()->with($member->phone)->andReturn([
            'transaction_id' => $transaction->id,
            'type' => 'bill',
        ]);
        $state->shouldReceive('clear')->once()->with($member->phone);

        $zApi = Mockery::mock(ZApiService::class);
        $zApi->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Conta confirmada')))
            ->andReturnTrue();
        $this->app->instance(ZApiService::class, $zApi);

        $balance = Mockery::mock(BalanceService::class);
        $balance->shouldReceive('updateBalance')
            ->once()
            ->with(Mockery::on(fn (Transaction $updated) => $updated->id === $transaction->id))
            ->andReturnNull();
        $this->app->instance(BalanceService::class, $balance);

        $router = $this->makeRouter($state, Mockery::mock(RuleBasedClassifierService::class));

        $router->handle($member, 'SIM', null);

        $transaction->refresh();

        $this->assertSame('confirmed', $transaction->status);
        $this->assertSame('120.50', $transaction->house_amount);
    }

    public function test_bill_rejection_switches_back_to_manual_value_flow(): void
    {
        [$member, $transaction] = $this->makeBillTransaction();

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('getState')->once()->with($member->phone)->andReturn(ConversationState::STATE_WAITING_CONFIRMATION);
        $state->shouldReceive('getData')->once()->with($member->phone)->andReturn([
            'transaction_id' => $transaction->id,
            'type' => 'bill',
        ]);
        $state->shouldReceive('setState')->once()->with($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $state->shouldReceive('setData')->once()->with($member->phone, [
            'transaction_id' => $transaction->id,
            'type' => 'bill',
        ]);

        $zApi = Mockery::mock(ZApiService::class);
        $zApi->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'valor correto')))
            ->andReturnTrue();
        $this->app->instance(ZApiService::class, $zApi);

        $balance = Mockery::mock(BalanceService::class);
        $balance->shouldNotReceive('updateBalance');
        $this->app->instance(BalanceService::class, $balance);

        $router = $this->makeRouter($state, Mockery::mock(RuleBasedClassifierService::class));

        $router->handle($member, 'não', null);

        $transaction->refresh();

        $this->assertSame('processed', $transaction->status);
        $this->assertSame('0.00', $transaction->house_amount);
    }

    public function test_receipt_correction_updates_item_category_and_teaches_classifier(): void
    {
        [$member, $transaction] = $this->makeReceiptTransaction();

        $item = TransactionItem::create([
            'transaction_id' => $transaction->id,
            'name' => 'Sorvete',
            'value' => 18.90,
            'category' => 'house',
            'confirmed' => false,
        ]);

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('getState')->once()->with($member->phone)->andReturn(ConversationState::STATE_WAITING_CONFIRMATION);
        $state->shouldReceive('getData')->once()->with($member->phone)->andReturn([
            'transaction_id' => $transaction->id,
            'correction_mode' => true,
        ]);
        $state->shouldReceive('setData')->once()->with($member->phone, [
            'transaction_id' => $transaction->id,
            'correction_mode' => true,
        ]);

        $rules = Mockery::mock(RuleBasedClassifierService::class);
        $rules->shouldReceive('learn')->once()->with('Sorvete', 'personal', 'user', 100);

        $zApi = Mockery::mock(ZApiService::class);
        $zApi->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Ajustei *Sorvete* para *pessoal*')))
            ->andReturnTrue();
        $this->app->instance(ZApiService::class, $zApi);

        $router = $this->makeRouter($state, $rules);

        $router->handle($member, 'Sorvete = pessoal', null);

        $item->refresh();

        $this->assertSame('personal', $item->category);
        $this->assertTrue($item->confirmed);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeRouter(ConversationState $state, RuleBasedClassifierService $rules): BotRouter
    {
        return new BotRouter(
            $state,
            Mockery::mock(HelpHandler::class),
            Mockery::mock(ReceiptHandler::class),
            Mockery::mock(ClassifyHandler::class),
            Mockery::mock(BillHandler::class),
            $rules,
        );
    }

    private function makeBillTransaction(): array
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
        ]);

        $member = Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);

        $transaction = Transaction::create([
            'group_id' => $group->id,
            'member_id' => $member->id,
            'type' => 'bill',
            'description' => 'Conta de luz',
            'total_amount' => 120.50,
            'house_amount' => 0,
            'status' => 'processed',
            'reference_month' => now()->startOfMonth(),
        ]);

        return [$member, $transaction];
    }

    private function makeReceiptTransaction(): array
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
        ]);

        $member = Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);

        $transaction = Transaction::create([
            'group_id' => $group->id,
            'member_id' => $member->id,
            'type' => 'receipt',
            'description' => 'Nota fiscal',
            'total_amount' => 18.90,
            'house_amount' => 0,
            'status' => 'processed',
            'reference_month' => now()->startOfMonth(),
        ]);

        return [$member, $transaction];
    }
}
