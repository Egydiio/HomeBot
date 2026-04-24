<?php

namespace Tests\Unit;

use App\Jobs\ProcessReceiptImage;
use App\Models\Group;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\ReceiptClassificationPipeline;
use App\Services\WhatsApp\WhatsAppClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessReceiptImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_manual_value_when_pipeline_returns_no_items(): void
    {
        [$transaction, $member] = $this->makeTransaction('receipt');

        $pipeline = Mockery::mock(ReceiptClassificationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->with('https://example.com/note.jpg')
            ->andReturn([
                'classified' => [],
                'ambiguous' => [],
                'total' => null,
            ]);

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Qual foi o valor total')))
            ->andReturnTrue();

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('setState')
            ->once()
            ->with($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $state->shouldReceive('setData')
            ->once()
            ->with($member->phone, ['transaction_id' => $transaction->id]);

        $classifier = Mockery::mock(ClassifyHandler::class);
        $classifier->shouldNotReceive('handle');

        $job = new ProcessReceiptImage($transaction->id, 'https://example.com/note.jpg', $member->phone);
        $job->handle($pipeline, $whatsapp, $state, $classifier);

        $transaction->refresh();

        $this->assertSame('pending', $transaction->status);
        $this->assertSame('0.00', $transaction->total_amount);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeTransaction(string $type): array
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
            'active' => true,
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
            'type' => $type,
            'description' => 'Nota fiscal',
            'total_amount' => 0,
            'house_amount' => 0,
            'status' => 'pending',
            'reference_month' => now()->startOfMonth(),
        ]);

        return [$transaction, $member];
    }
}
