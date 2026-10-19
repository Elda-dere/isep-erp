<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            // SHA-256 hex hash only, never the raw token (DR-08).
            $table->string('token_hash', 255)->unique(); // ASSUMPTION: unique for fast lookup
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};