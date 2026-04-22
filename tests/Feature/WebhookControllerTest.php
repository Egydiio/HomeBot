<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use App\Services\Bot\BotRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_routes_a_valid_text_message_to_the_bot(): void
    {
        $member = $this->makeMember('5511999999999');

        $router = Mockery::mock(BotRouter::class);
        $router->shouldReceive('handle')
            ->once()
            ->with(
                Mockery::on(fn (Member $resolved) => $resolved->is($member)),
                'saldo',
                null,
            );

        $this->app->instance(BotRouter::class, $router);

        $response = $this->postJson('/api/webhook', [
            'phone' => '5511999999999@c.us',
            'text' => ['message' => 'saldo'],
            'fromMe' => false,
            'isGroup' => false,
            'type' => 'chat',
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_it_ignores_group_or_from_me_messages(): void
    {
        $router = Mockery::mock(BotRouter::class);
        $router->shouldNotReceive('handle');
        $this->app->instance(BotRouter::class, $router);

        $response = $this->postJson('/api/webhook', [
            'phone' => '5511999999999',
            'text' => ['message' => 'oi'],
            'fromMe' => true,
            'isGroup' => false,
            'type' => 'chat',
        ]);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_it_returns_unknown_member_when_phone_is_not_registered(): void
    {
        $router = Mockery::mock(BotRouter::class);
        $router->shouldNotReceive('handle');
        $this->app->instance(BotRouter::class, $router);

        $response = $this->postJson('/api/webhook', [
            'phone' => '5511888888888',
            'text' => ['message' => 'oi'],
            'fromMe' => false,
            'isGroup' => false,
            'type' => 'chat',
        ]);

        $response->assertOk()->assertJson(['status' => 'unknown_member']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeMember(string $phone): Member
    {
        $group = Group::create([
            'name' => 'Casa Teste',
            'slug' => 'casa-teste',
            'active' => true,
        ]);

        return Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => $phone,
            'split_percent' => 50,
            'active' => true,
        ]);
    }
}
