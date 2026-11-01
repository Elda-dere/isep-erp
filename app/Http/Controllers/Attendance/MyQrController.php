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

        return Inertia::render('Attendance/MyQr', [
            'state' => 'ready',
            'employeeName' => $employee->fullName(),
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