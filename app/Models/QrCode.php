<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    // Only created_at exists. The attendance service sets it with now() (UTC) itself.
    public $timestamps = false;

    protected $fillable = ['employee_id', 'token_hash', 'expires_at', 'revoked_at', 'created_at'];

    // The stored hash must never be sent to a page.
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}