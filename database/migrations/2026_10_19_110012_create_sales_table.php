<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 60)->unique();
            // Not nullable: anonymous sales use the seeded Walk-in customer (SAL-01).
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('ETB');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};