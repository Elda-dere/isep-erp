<?php

namespace Tests\Unit;

use App\Support\IpRange;
use PHPUnit\Framework\TestCase;

class IpRangeTest extends TestCase
{
    public function test_an_ipv4_range_includes_its_first_and_last_address(): void
    {
        $range = '192.168.1.0/24';

        $this->assertTrue(IpRange::contains('192.168.1.0', $range));
        $this->assertTrue(IpRange::contains('192.168.1.100', $range));
        $this->assertTrue(IpRange::contains('192.168.1.255', $range));
        $this->assertFalse(IpRange::contains('192.168.2.0', $range));
        $this->assertFalse(IpRange::contains('192.168.0.255', $range));
    }

    public function test_ipv4_prefixes_that_are_not_whole_bytes_work(): void
    {
        $this->assertTrue(IpRange::contains('192.168.1.127', '192.168.1.0/25'));
        $this->assertFalse(IpRange::contains('192.168.1.128', '192.168.1.0/25'));
        $this->assertTrue(IpRange::contains('10.255.255.255', '10.0.0.0/8'));
        $this->assertFalse(IpRange::contains('11.0.0.0', '10.0.0.0/8'));
        $this->assertTrue(IpRange::contains('172.31.255.255', '172.16.0.0/12'));
        $this->assertFalse(IpRange::contains('172.32.0.0', '172.16.0.0/12'));
    }

    public function test_a_single_address_range_matches_only_that_address(): void
    {
        $this->assertTrue(IpRange::contains('203.0.113.7', '203.0.113.7/32'));
        $this->assertFalse(IpRange::contains('203.0.113.8', '203.0.113.7/32'));
    }

    public function test_ipv6_ranges_work(): void
    {
        $this->assertTrue(IpRange::contains('2001:db8::1', '2001:db8::/32'));
        $this->assertTrue(IpRange::contains('2001:db8:ffff::1', '2001:db8::/32'));
        $this->assertFalse(IpRange::contains('2001:db9::1', '2001:db8::/32'));
        $this->assertTrue(IpRange::contains('::1', '::1/128'));
        $this->assertFalse(IpRange::contains('::2', '::1/128'));
    }

    public function test_families_do_not_mix_but_a_mapped_ipv4_address_counts_as_ipv4(): void
    {
        $this->assertFalse(IpRange::contains('192.168.1.5', '2001:db8::/32'));
        $this->assertFalse(IpRange::contains('2001:db8::1', '192.168.1.0/24'));
        $this->assertTrue(IpRange::contains('::ffff:192.168.1.5', '192.168.1.0/24'));
        $this->assertFalse(IpRange::contains('::ffff:192.168.2.5', '192.168.1.0/24'));
    }

    public function test_garbage_never_matches(): void
    {
        $this->assertFalse(IpRange::contains('not-an-ip', '192.168.1.0/24'));
        $this->assertFalse(IpRange::contains('', '192.168.1.0/24'));
        $this->assertFalse(IpRange::contains('192.168.1.5', 'garbage'));
        $this->assertFalse(IpRange::contains('192.168.1.5', '192.168.1.0'));
    }

    public function test_valid_ranges_are_accepted(): void
    {
        foreach (['192.168.1.0/24', '10.0.0.0/8', '203.0.113.7/32', '2001:db8::/32', '::1/128'] as $range) {
            $this->assertTrue(IpRange::valid($range), "{$range} should be valid");
        }
    }

    public function test_malformed_ranges_are_rejected(): void
    {
        $bad = [
            '', '192.168.1.0', '192.168.1.0/', '192.168.1.0/33', '192.168.1.0/-1', '192.168.1.0/ab',
            '999.1.1.1/24', '10.0.0.0/24/1', ' 10.0.0.0/24', '2001:db8::/129', '2001:db8::', '/24',
        ];

        foreach ($bad as $range) {
            $this->assertFalse(IpRange::valid($range), "'{$range}' should be invalid");
        }
    }

    public function test_normalize_gives_the_first_address_of_the_range(): void
    {
        $this->assertSame('192.168.1.0/24', IpRange::normalize('192.168.1.77/24'));
        $this->assertSame('10.1.2.3/32', IpRange::normalize('10.1.2.3/32'));
        $this->assertSame('2001:db8::/32', IpRange::normalize('2001:DB8::1/32'));
        $this->assertNull(IpRange::normalize('garbage'));
    }

    public function test_the_prefix_length_is_reported(): void
    {
        $this->assertSame(24, IpRange::prefix('192.168.1.0/24'));
        $this->assertSame(0, IpRange::prefix('0.0.0.0/0'));
        $this->assertNull(IpRange::prefix('garbage'));
    }
}