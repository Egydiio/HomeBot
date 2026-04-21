<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 120)->unique();
            $table->enum('category', ['house', 'personal']);
            // manual = seeded by hand, ai = learned from AI response, user = confirmed by end-user
            $table->enum('source', ['manual', 'ai', 'user'])->default('manual');
            // 0-100; items learned from AI start lower until user confirms
            $table->unsignedSmallInteger('confidence')->default(100);
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }
};
