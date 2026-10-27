<?php

namespace App\Support;

/**
 * Builds the next document number for a prefix: PO-2026-0001, PO-2026-0002 ...
 * Goods receipts and sales will reuse it with their own prefixes.
 */
class DocumentNumber
{
    // Call it inside a database transaction, so the lock below is held until the new row is saved.
    // $column comes from our own code (never from user input), so it is safe in the SQL below.
    public static function next(string $modelClass, string $column, string $prefix, int $width = 4): string
    {
        // Find the highest number with this prefix. The suffix is compared as a NUMBER, because as
        // text "PO-2026-10000" would sort before "PO-2026-9999".
        $last = $modelClass::query()
            ->where($column, 'like', $prefix . '%')
            ->orderByRaw("CAST(SUBSTRING({$column}, ?) AS UNSIGNED) DESC", [strlen($prefix) + 1])
            ->lockForUpdate()
            ->value($column);

        $number = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        // 7 becomes "0007". Numbers longer than $width (like 10000) simply grow.
        return $prefix . str_pad((string) $number, $width, '0', STR_PAD_LEFT);
    }
}
