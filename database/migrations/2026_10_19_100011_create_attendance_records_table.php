<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('attendance_date');
            $table->dateTime('check_in_at'); // ASSUMPTION: row is created by the first scan
            $table->dateTime('check_out_at')->nullable();
            $table->string('status', 30)->default('present');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['employee_id', 'attendance_date']); // one record per employee per day
            $table->index('attendance_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};