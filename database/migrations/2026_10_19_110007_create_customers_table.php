<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180)->index();
            $table->string('phone', 30)->nullable(); // ASSUMPTION
            $table->string('email', 190)->nullable(); // ASSUMPTION
            $table->text('address')->nullable(); // ASSUMPTION
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};