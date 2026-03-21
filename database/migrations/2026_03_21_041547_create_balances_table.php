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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debtor_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('creditor_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('reference_month');
            $table->timestamps();

            $table->unique(['debtor_id', 'creditor_id', 'reference_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
