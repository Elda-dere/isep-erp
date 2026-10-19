<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->decimal('quantity', 12, 3); // signed: positive in, negative out (A-04)
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
            // ASSUMPTION: nullable, because scheduled jobs (sale expiry) have no acting user.
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'warehouse_id']);
            $table->index('movement_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};