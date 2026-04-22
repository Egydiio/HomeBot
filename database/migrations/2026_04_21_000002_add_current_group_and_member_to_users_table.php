<?php

use App\Models\Group;
use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_group_id')->nullable()->after('password')->constrained('groups')->nullOnDelete();
            $table->foreignId('current_member_id')->nullable()->after('current_group_id')->constrained('members')->nullOnDelete();
        });

        $singleActiveGroup = Group::where('active', true)->get();

        if ($singleActiveGroup->count() === 1) {
            $group = $singleActiveGroup->first();

            DB::table('users')
                ->whereNull('current_group_id')
                ->update(['current_group_id' => $group->id]);

            foreach (DB::table('users')->whereNull('current_member_id')->get() as $user) {
                $member = Member::where('group_id', $group->id)
                    ->where('name', $user->name)
                    ->first();

                if ($member) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['current_member_id' => $member->id]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_member_id');
            $table->dropConstrainedForeignId('current_group_id');
        });
    }
};
