<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // The diagram says varchar(CIDR) with no length. 50 fits any IPv4 or IPv6 CIDR. ASSUMPTION
            $table->string('ip_range', 50);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_networks');
    }
};