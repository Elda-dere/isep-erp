<?php

namespace App\Http\Controllers\Attendance;

use App\Models\Employee;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyQrController
{
    public function __construct(private QrTokenService $tokens) {}

    public function show(Request $request): Response
    {
        $employee = Employee::query()->where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return Inertia::render('Attendance/MyQr', [
                'state' => 'no_employee',
                'message' => 'Your login is not linked to an employee record. Ask HR to link it.',
            ]);
        }

        // ATT-03 says "employee active", read literally: only the status "active" can check in.
        if ($employee->employment_status !== 'active') {
            return Inertia::render('Attendance/MyQr', [
                'state' => 'blocked',
                'employeeName' => $employee->fullName(),
                'message' => 'Your employment status is "'.str_replace('_', ' ', $employee->employment_status).'", so you cannot check in. Ask HR.',
            ]);
        }

        $ips = [];
        @exec('ipconfig', $lines);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if (preg_match('/IPv4 Address.*:\s*([0-9\.]+)/', $line, $matches)) {
                    $ip = trim($matches[1]);
                    if (! str_starts_with($ip, '127.') && ! str_starts_with($ip, '169.') && ! in_array($ip, $ips, true)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        usort($ips, function ($a, $b) {
            $isWifiA = str_starts_with($a, '10.4.') || str_starts_with($a, '192.168.1.');
            $isWifiB = str_starts_with($b, '10.4.') || str_starts_with($b, '192.168.1.');
            return $isWifiB <=> $isWifiA;
        });

        $primaryIp = $ips[0] ?? '127.0.0.1';

        return Inertia::render('Attendance/MyQr', [
            'state' => 'ready',
            'employeeName' => $employee->fullName(),
            'localIp' => $primaryIp,
            'availableIps' => $ips,
            // A closure, so a new token is made only when the page asks for "qr".
            'qr' => function () use ($employee) {
                $issued = $this->tokens->issue($employee);

                return [
                    'token' => $issued['token'],
                    'expires_in' => $issued['expires_in'],
                    'lifetime' => $issued['expires_in'],
                ];
            },
        ]);
    }
}