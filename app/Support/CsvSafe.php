<?php

namespace App\Support;

/**
 * A cell that starts with = + - @ tab or return could run as a formula when a CSV file is opened
 * in a spreadsheet, so such a cell gets a leading apostrophe.
 */
final class CsvSafe
{
    public static function cell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}