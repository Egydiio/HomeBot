<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use App\Models\MonthlyClose;
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
use App\Services\WhatsApp\WhatsAppClientInterface;
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

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Conta confirmada')))
            ->andReturnTrue();
        $this->app->instance(WhatsAppClientInterface::class, $whatsapp);

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

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'valor correto')))
            ->andReturnTrue();
        $this->app->instance(WhatsAppClientInterface::class, $whatsapp);

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

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Ajustei *Sorvete* para *pessoal*')))
            ->andReturnTrue();
        $this->app->instance(WhatsAppClientInterface::class, $whatsapp);

        $router = $this->makeRouter($state, $rules);

        $router->handle($member, 'Sorvete = pessoal', null);

        $item->refresh();

        $this->assertSame('personal', $item->category);
        $this->assertTrue($item->confirmed);
    }

    public function test_payment_confirmation_marks_single_open_close_as_paid(): void
    {
        [$member, $creditor, $close] = $this->makeMonthlyClose();

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('getState')->once()->with($member->phone)->andReturn(ConversationState::STATE_IDLE);
        $state->shouldReceive('clear')->once()->with($member->phone);

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Pagamento registrado')))
            ->andReturnTrue();
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($creditor->phone, Mockery::on(fn (string $message) => str_contains($message, 'marcou como pago')))
            ->andReturnTrue();
        $this->app->instance(WhatsAppClientInterface::class, $whatsapp);

        $router = $this->makeRouter($state, Mockery::mock(RuleBasedClassifierService::class));

        $router->handle($member, 'paguei', null);

        $close->refresh();

        $this->assertSame('paid', $close->status);
        $this->assertNotNull($close->paid_at);
    }

    public function test_payment_confirmation_asks_for_selection_when_there_are_multiple_open_closes(): void
    {
        [$member, , $firstClose] = $this->makeMonthlyClose();
        [, , $secondClose] = $this->makeMonthlyClose('2026-02-01', '5511888888888');

        $expectedIds = [$secondClose->id, $firstClose->id];

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('getState')->once()->with($member->phone)->andReturn(ConversationState::STATE_IDLE);
        $state->shouldReceive('setState')->once()->with($member->phone, ConversationState::STATE_WAITING_PAYMENT_SELECTION);
        $state->shouldReceive('setData')->once()->with($member->phone, Mockery::on(
            fn (array $data) => ($data['monthly_close_ids'] ?? null) === $expectedIds
        ));

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(
                fn (string $message) => str_contains($message, 'Qual delas você pagou?')
                    && str_contains($message, '1.')
                    && str_contains($message, '2.')
            ))
            ->andReturnTrue();
        $this->app->instance(WhatsAppClientInterface::class, $whatsapp);

        $router = $this->makeRouter($state, Mockery::mock(RuleBasedClassifierService::class));

        $router->handle($member, 'paguei', null);

        $firstClose->refresh();
        $secondClose->refresh();

        $this->assertSame('charged', $firstClose->status);
        $this->assertSame('charged', $secondClose->status);
    }

    public function test_payment_selection_confirms_the_chosen_close(): void
    {
        [$member, $creditor, $firstClose] = $this->makeMonthlyClose();
        [, , $secondClose] = $this->makeMonthlyClose('2026-02-01', '5511888888888');

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('getState')->once()->with($member->phone)->andReturn(ConversationState::STATE_WAITING_PAYMENT_SELECTION);
        $state->shouldReceive('getData')->once()->with($member->phone)->andReturn([
            'monthly_close_ids' => [$secondClose->id, $firstClose->id],
        ]);
        $state->shouldReceive('clear')->once()->with($member->phone);

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Pagamento registrado')))
            ->andReturnTrue();
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($creditor->phone, Mockery::on(fn (string $message) => str_contains($message, 'marcou como pago')))
            ->andReturnTrue();
        $this->app->instance(WhatsAppClientInterface::class, $whatsapp);

        $router = $this->makeRouter($state, Mockery::mock(RuleBasedClassifierService::class));

        $router->handle($member, '2', null);

        $firstClose->refresh();
        $secondClose->refresh();

        $this->assertSame('paid', $firstClose->status);
        $this->assertNotNull($firstClose->paid_at);
        $this->assertSame('charged', $secondClose->status);
        $this->assertNull($secondClose->paid_at);
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

    private function makeMonthlyClose(string $referenceMonth = '2026-01-01', string $creditorPhone = '5511777777777'): array
    {
        $group = Group::firstOrCreate([
            'slug' => 'casa-teste',
        ], [
            'name' => 'Casa Teste',
        ]);

        $member = Member::firstOrCreate([
            'phone' => '5511999999999',
        ], [
            'group_id' => $group->id,
            'name' => 'Egydio',
            'split_percent' => 50,
            'active' => true,
        ]);

        $creditor = Member::firstOrCreate([
            'phone' => $creditorPhone,
        ], [
            'group_id' => $group->id,
            'name' => $creditorPhone === '5511777777777' ? 'Irmã' : 'Credor 2',
            'split_percent' => 50,
            'active' => true,
        ]);

        $close = MonthlyClose::create([
            'group_id' => $group->id,
            'reference_month' => $referenceMonth,
            'status' => 'charged',
            'amount' => 49.90,
            'debtor_id' => $member->id,
            'creditor_id' => $creditor->id,
            'charged_at' => now(),
        ]);

        return [$member, $creditor, $close];
    }
}
