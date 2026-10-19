<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->string('provider', 40)->default('chapa');
            $table->string('tx_ref', 100)->unique();
            $table->string('provider_transaction_id', 150)->nullable()->index(); // ASSUMPTION
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ETB');
            $table->string('status', 30)->default('pending');
            $table->text('checkout_url')->nullable(); // ASSUMPTION
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};