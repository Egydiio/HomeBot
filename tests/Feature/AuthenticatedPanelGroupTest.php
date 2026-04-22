<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedPanelGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_uses_the_authenticated_users_group(): void
    {
        $groupA = Group::create([
            'name' => 'Casa Azul',
            'slug' => 'casa-azul',
            'active' => true,
        ]);

        $groupB = Group::create([
            'name' => 'Casa Vermelha',
            'slug' => 'casa-vermelha',
            'active' => true,
        ]);

        $member = Member::create([
            'group_id' => $groupB->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Egydio',
            'email' => 'egydio@example.com',
            'password' => 'secret',
            'current_group_id' => $groupB->id,
            'current_member_id' => $member->id,
        ]);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('Casa Vermelha');
        $response->assertDontSee('Casa Azul');
    }
}
