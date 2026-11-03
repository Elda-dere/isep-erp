<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Small helpers that turn stored UTC times into what a person reads on screen.
 */
final class AttendanceFormat
{
    /** A time of day such as 09:05 in business time, or null when there is no time. */
    public static function time(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(config('erp.timezone'))->format('H:i');
    }

    /** Check-out minus check-in, written like 8h 05m. Null until the person has checked out. */
    public static function worked(?CarbonInterface $in, ?CarbonInterface $out): ?string
    {
        if ($in === null || $out === null) {
            return null;
        }

        $minutes = max(0, intdiv($out->getTimestamp() - $in->getTimestamp(), 60));

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}