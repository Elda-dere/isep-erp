<?php

namespace App\Http\Controllers\Attendance;

use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController
{
    public function __construct(private AttendanceService $attendance) {}

    public function show(): Response
    {
        return Inertia::render('Attendance/Scanner');
    }

    /**
     * The one JSON endpoint of the app: the scanner page must show the result without reloading.
     * It uses the same login, permission and CSRF protection as every other route.
     */
    public function store(Request $request): JsonResponse
    {
        $raw = $request->input('token');
        $token = is_string($raw) ? trim($raw) : '';

        // Anything that is not shaped like one of our codes is somebody else's QR code.
        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return response()->json([
                'ok' => false,
                'code' => 'not_attendance_code',
                'message' => 'This is not an attendance code.',
            ], 422);
        }

        $result = $this->attendance->scan($token, (string) $request->ip(), $request->userAgent());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}