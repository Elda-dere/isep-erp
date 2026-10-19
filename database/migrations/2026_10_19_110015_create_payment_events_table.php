<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('provider_event_id', 150)->unique(); // duplicate deliveries ignored (PAY-07)
            $table->string('event_type', 80);
            $table->json('payload');
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();

            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};