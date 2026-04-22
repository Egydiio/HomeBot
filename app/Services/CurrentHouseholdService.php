<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Member;
use App\Models\User;

class CurrentHouseholdService
{
    public function groupForUser(?User $user): ?Group
    {
        if (!$user) {
            return null;
        }

        $group = $user->currentGroup;

        if ($group && $group->active) {
            return $group;
        }

        if ($user->current_member_id) {
            $member = $user->currentMember?->loadMissing('group');

            if ($member?->group?->active) {
                if ($user->current_group_id !== $member->group_id) {
                    $user->forceFill(['current_group_id' => $member->group_id])->save();
                }

                return $member->group;
            }
        }

        $activeGroups = Group::where('active', true)->get();

        if ($activeGroups->count() === 1) {
            $group = $activeGroups->first();

            $updates = ['current_group_id' => $group->id];

            if (!$user->current_member_id) {
                $member = Member::where('group_id', $group->id)
                    ->where('name', $user->name)
                    ->first();

                if ($member) {
                    $updates['current_member_id'] = $member->id;
                }
            }

            $user->forceFill($updates)->save();

            return $group;
        }

        return null;
    }

    public function memberForUser(?User $user): ?Member
    {
        if (!$user) {
            return null;
        }

        $member = $user->currentMember;

        if ($member && $member->active) {
            return $member;
        }

        $group = $this->groupForUser($user);

        if (!$group) {
            return null;
        }

        $member = Member::where('group_id', $group->id)
            ->where('active', true)
            ->where('name', $user->name)
            ->first();

        if ($member) {
            $user->forceFill(['current_member_id' => $member->id])->save();
        }

        return $member;
    }
}
