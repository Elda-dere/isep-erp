<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->index();
            $table->string('location', 255)->nullable(); // ASSUMPTION
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};