<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    public const STATUSES = ['active', 'on_leave', 'suspended', 'terminated'];

    /** Moving to one of these revokes the employee's QR codes and blocks attendance scans (EMP-06). */
    public const BLOCKING_STATUSES = ['suspended', 'terminated'];

    protected $fillable = [
        'user_id',
        'position_id',
        'employee_number',
        'first_name',
        'last_name',
        'phone',
        'hire_date',
        'employment_status',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}