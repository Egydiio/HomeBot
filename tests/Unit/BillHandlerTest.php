<?php

namespace Tests\Unit;

use App\Jobs\ProcessReceiptImage;
use App\Models\Group;
use App\Models\Member;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\BillHandler;
use App\Services\ReceiptImageGuardService;
use App\Services\ZApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BillHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_manual_value_without_dispatching_ocr_for_invalid_bill_images(): void
    {
        Queue::fake();

        $member = $this->makeMember();

        $zApi = Mockery::mock(ZApiService::class);
        $zApi->shouldReceive('sendText')
            ->once()
            ->with($member->phone, Mockery::on(fn (string $message) => str_contains($message, 'Me manda o valor da conta')))
            ->andReturnTrue();

        $state = Mockery::mock(ConversationState::class);
        $state->shouldReceive('setState')
            ->once()
            ->with($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $state->shouldReceive('setData')
            ->once()
            ->with($member->phone, Mockery::on(fn (array $data) => ($data['type'] ?? null) === 'bill' && isset($data['transaction_id'])));

        $imageGuard = Mockery::mock(ReceiptImageGuardService::class);
        $imageGuard->shouldReceive('validateIncomingMedia')
            ->once()
            ->andReturn('invalid_mime_type');

        $handler = new BillHandler($zApi, $state, $imageGuard);

        $handler->handle($member, [
            'type' => 'image',
            'url' => 'https://example.com/conta.txt',
            'mime_type' => 'text/plain',
        ]);

        Queue::assertNotPushed(ProcessReceiptImage::class);

        $this->assertDatabaseHas('transactions', [
            'group_id' => $member->group_id,
            'member_id' => $member->id,
            'type' => 'bill',
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
            'phone' => '5511888888888',
            'split_percent' => 50,
            'active' => true,
        ]);
    }
}
