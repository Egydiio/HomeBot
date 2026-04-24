<?php

namespace Tests\Unit;

use App\Jobs\ProcessReceiptImage;
use App\Models\Group;
use App\Models\Member;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\ReceiptHandler;
use App\Services\ReceiptImageGuardService;
use App\Services\WhatsApp\WhatsAppClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ReceiptHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_manual_value_without_dispatching_ocr_for_invalid_images(): void
    {
        Queue::fake();

        $member = $this->makeMember();

        $whatsapp = Mockery::mock(WhatsAppClientInterface::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'nao parece valida para OCR')))
            ->andReturnTrue();

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('setState')
            ->once()
            ->with($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $state->shouldReceive('setData')
            ->once()
            ->with($member->phone, Mockery::on(fn (array $data) => ($data['type'] ?? null) === 'receipt' && isset($data['transaction_id'])));

        $imageGuard = Mockery::mock(ReceiptImageGuardService::class);
        $imageGuard->shouldReceive('validateIncomingMedia')
            ->once()
            ->andReturn('image_too_small');

        $handler = new ReceiptHandler($whatsapp, $state, $imageGuard);

        $handler->handle($member, [
            'type' => 'image',
            'url' => 'https://example.com/tiny.jpg',
            'size' => 100,
        ]);

        Queue::assertNotPushed(ProcessReceiptImage::class);

        $this->assertDatabaseHas('transactions', [
            'group_id' => $member->group_id,
            'member_id' => $member->id,
            'type' => 'receipt',
            'status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeMember(): Member
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
            'active' => true,
        ]);

        return Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);
    }
}
