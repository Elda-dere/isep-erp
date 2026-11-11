<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentEvent extends Model
{
    public $timestamps = false; // the table has received_at and processed_at instead

    protected $fillable = ['payment_id', 'provider_event_id', 'event_type', 'payload', 'received_at', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // Payment history is append-only (SRS DR-11): the payload is never altered, an event is never deleted,
    // and the only change allowed is setting "processed_at" ONCE.
    protected static function booted(): void
    {
        static::updating(function (PaymentEvent $event) {
            if (array_keys($event->getDirty()) !== ['processed_at'] || $event->getOriginal('processed_at') !== null) {
                throw new LogicException('Payment events cannot be changed.');
            }
        });

        static::deleting(fn () => throw new LogicException('Payment events cannot be deleted.'));
    }
}