<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->string('receipt_number', 60)->unique();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('received_at'); // ASSUMPTION: set when the draft receipt is created
            $table->string('status', 30)->default('draft');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};