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
        Schema::create('monthly_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->enum('status', ['pending', 'charged', 'paid'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->foreignId('debtor_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('creditor_id')->constrained('members')->cascadeOnDelete();
            $table->timestamp('charged_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_closes');
    }
};
