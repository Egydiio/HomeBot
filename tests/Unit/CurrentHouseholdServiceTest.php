<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use App\Services\CurrentHouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentHouseholdServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_users_linked_group_and_member(): void
    {
        $group = Group::create([
            'name' => 'Casa Azul',
            'slug' => 'casa-azul',
            'active' => true,
        ]);

        $member = Member::create([
            'group_id' => $group->id,
            'name' => 'Egydio',
            'phone' => '5511999999999',
            'split_percent' => 50,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Egydio',
            'email' => 'egydio@example.com',
            'password' => 'secret',
            'current_group_id' => $group->id,
            'current_member_id' => $member->id,
        ]);

        $service = app(CurrentHouseholdService::class);

        $this->assertTrue($service->groupForUser($user)->is($group));
        $this->assertTrue($service->memberForUser($user)->is($member));
    }

    public function test_it_backfills_current_group_when_there_is_only_one_active_group(): void
    {
        $group = Group::create([
            'name' => 'Casa Verde',
            'slug' => 'casa-verde',
            'active' => true,
        ]);

        $member = Member::create([
            'group_id' => $group->id,
            'name' => 'Maria',
            'phone' => '5511888888888',
            'split_percent' => 50,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'secret',
        ]);

        $service = app(CurrentHouseholdService::class);
        $resolvedGroup = $service->groupForUser($user);
        $resolvedMember = $service->memberForUser($user);

        $user->refresh();

        $this->assertTrue($resolvedGroup->is($group));
        $this->assertTrue($resolvedMember->is($member));
        $this->assertSame($group->id, $user->current_group_id);
        $this->assertSame($member->id, $user->current_member_id);
    }
}
