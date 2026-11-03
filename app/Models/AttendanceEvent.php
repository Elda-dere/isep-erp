<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    public const CHECK_IN = 'check_in';
    public const CHECK_OUT = 'check_out';
    public const MANUAL_EDIT = 'manual_edit';

    // The table has no created_at or updated_at; recorded_at is the time of the event.
    public $timestamps = false;

    protected $fillable = [
        'attendance_record_id',
        'event_type',
        'recorded_at',
        'ip_address',
        'method',
        'device_info',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }
}