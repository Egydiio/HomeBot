<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['receipt', 'bill']);
            $table->string('description')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('house_amount', 10, 2);
            $table->string('receipt_image')->nullable();
            $table->enum('status', ['pending', 'processed', 'confirmed'])->default('pending');
            $table->date('reference_month');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
