<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    private const MASK = '********';

    /** A value is masked when its key contains any of these words (AUD-05). */
    private const SECRET_WORDS = [
        'password', 'token', 'secret', 'authorization', 'signature', 'hash', 'api_key', 'private_key',
    ];

    /**
     * Write one audit row.
     *
     * The acting user and the IP address are filled in automatically.
     * System actions (scheduled jobs, webhooks) have no signed-in user, so user_id stays empty (AUD-03).
     *
     * Example: AuditLogger::log('product.price_changed', 'product', $product->id, $old, $new);
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old === null ? null : self::mask($old),
            'new_values' => $new === null ? null : self::mask($new),
            'ip_address' => request()->ip(),
            // Set in PHP (UTC), not by the database default, so the time zone cannot differ (DR-07).
            'created_at' => now(),
        ]);
    }

    private static function mask(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSecretKey($key)) {
                $result[$key] = self::MASK;
            } elseif (is_array($value)) {
                $result[$key] = self::mask($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SECRET_WORDS as $word) {
            if (str_contains($key, $word)) {
                return true;
            }
        }

        return false;
    }
}