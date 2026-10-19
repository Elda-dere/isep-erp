<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->restrictOnDelete();
            $table->string('event_type', 30);
            $table->dateTime('recorded_at');
            $table->string('ip_address', 45)->nullable(); // ASSUMPTION
            $table->string('method', 30);
            $table->string('device_info', 255)->nullable(); // ASSUMPTION

            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};