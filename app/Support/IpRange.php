<?php

namespace App\Support;

/**
 * Works with CIDR ranges such as 192.168.1.0/24 or 2001:db8::/32 (IPv4 and IPv6).
 */
final class IpRange
{
    /** True when the text is a complete range: an address, a slash and a prefix length. */
    public static function valid(string $cidr): bool
    {
        return self::parse($cidr) !== null;
    }

    /** The prefix length (the number after the slash), or null when the range is not valid. */
    public static function prefix(string $cidr): ?int
    {
        return self::parse($cidr)[1] ?? null;
    }

    /** The range written from its first address, for example 192.168.1.77/24 becomes 192.168.1.0/24. */
    public static function normalize(string $cidr): ?string
    {
        $parsed = self::parse($cidr);

        if ($parsed === null) {
            return null;
        }

        [$network, $prefix] = $parsed;

        return inet_ntop(self::mask($network, $prefix)).'/'.$prefix;
    }

    /** Is this client address inside the range? Anything invalid is simply "no". */
    public static function contains(string $ip, string $cidr): bool
    {
        $parsed = self::parse($cidr);
        $address = self::clientAddress($ip);

        if ($parsed === null || $address === null) {
            return false;
        }

        [$network, $prefix] = $parsed;

        // An IPv4 address is never inside an IPv6 range, and the other way round.
        if (strlen($address) !== strlen($network)) {
            return false;
        }

        return self::mask($address, $prefix) === self::mask($network, $prefix);
    }

    /** @return array{0: string, 1: int}|null the address bytes and the prefix length */
    private static function parse(string $cidr): ?array
    {
        $parts = explode('/', $cidr);

        if (count($parts) !== 2) {
            return null;
        }

        [$address, $prefix] = $parts;

        $binary = self::pack($address);

        if ($binary === null || $prefix === '' || strlen($prefix) > 3 || ! ctype_digit($prefix)) {
            return null;
        }

        $number = (int) $prefix;

        if ($number > strlen($binary) * 8) {
            return null;
        }

        return [$binary, $number];
    }

    private static function pack(string $address): ?string
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $binary = inet_pton($address);

        return $binary === false ? null : $binary;
    }

    private static function clientAddress(string $ip): ?string
    {
        $binary = self::pack($ip);

        if ($binary === null) {
            return null;
        }

        // An IPv4 address reported in IPv6 form (::ffff:192.168.1.5) is treated as IPv4.
        if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10)."\xff\xff")) {
            return substr($binary, 12);
        }

        return $binary;
    }

    /** Keeps the first $prefix bits of the address and sets every other bit to zero. */
    private static function mask(string $binary, int $prefix): string
    {
        $result = '';

        for ($i = 0, $length = strlen($binary); $i < $length; $i++) {
            $bits = max(0, min(8, $prefix - ($i * 8)));
            $mask = $bits === 0 ? 0 : (0xFF << (8 - $bits)) & 0xFF;

            $result .= chr(ord($binary[$i]) & $mask);
        }

        return $result;
    }
}