<?php

namespace App\Http\Controllers\Attendance;

use App\Models\AttendanceSetting;
use App\Models\BiometricDevice;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceModesController
{
    public const SALARY_METHODS = [
        'Fixed 30 Days Month',
        'Actual Days of Month (28-31 Days)',
        'Fixed 26 Working Days (Excluding Sundays)',
        'Fixed 22 Working Days (Excluding Weekends)',
    ];

    public function index(): Response
    {
        $settings = AttendanceSetting::current();

        $branches = Branch::query()
            ->withCount('devices')
            ->orderBy('id')
            ->get();

        $devices = BiometricDevice::query()
            ->with('branch')
            ->orderBy('id')
            ->get()
            ->map(fn (BiometricDevice $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'serial_number' => $d->serial_number,
                'branch' => $d->branch?->name ?? 'Main Branch',
                'branch_id' => $d->branch_id,
                'status' => $d->status,
                'ip_address' => $d->ip_address,
                'last_sync' => $d->last_sync_at ? $d->last_sync_at->format('M j, Y g:i A') : 'Never',
            ]);

        return Inertia::render('Attendance/Modes', [
            'settings' => [
                'id' => $settings->id,
                'salary_calculation_method' => $settings->salary_calculation_method,
                'static_qr_enabled' => $settings->static_qr_enabled,
                'static_qr_token' => $settings->static_qr_token,
                'dynamic_qr_enabled' => $settings->dynamic_qr_enabled,
            ],
            'salaryMethods' => self::SALARY_METHODS,
            'branches' => $branches,
            'devices' => $devices,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'salary_calculation_method' => ['required', 'string', 'max:100'],
        ]);

        $settings = AttendanceSetting::current();
        $settings->update($validated);

        return back()->with('success', 'Attendance settings updated successfully.');
    }

    public function generateStaticToken(): RedirectResponse
    {
        $settings = AttendanceSetting::current();
        $settings->update([
            'static_qr_token' => bin2hex(random_bytes(32)),
        ]);

        return back()->with('success', 'New Static QR token generated successfully.');
    }

    public function toggleMode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:static,dynamic'],
        ]);

        $settings = AttendanceSetting::current();

        if ($validated['mode'] === 'static') {
            $settings->update(['static_qr_enabled' => ! $settings->static_qr_enabled]);
        } else {
            $settings->update(['dynamic_qr_enabled' => ! $settings->dynamic_qr_enabled]);
        }

        return back()->with('success', 'Capture mode status updated.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'string', 'max:50', 'unique:branches,branch_id'],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        Branch::create($validated);

        return back()->with('success', 'Branch created successfully.');
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return back()->with('success', 'Branch removed successfully.');
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:biometric_devices,serial_number'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['nullable', 'string', 'in:active,offline,disabled'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ]);

        BiometricDevice::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
            'last_sync_at' => now(),
        ]);

        return back()->with('success', 'Biometric device registered successfully.');
    }

    public function destroyDevice(BiometricDevice $device): RedirectResponse
    {
        $device->delete();

        return back()->with('success', 'Biometric device deleted successfully.');
    }

    public function syncDevice(BiometricDevice $device): RedirectResponse
    {
        $device->update(['last_sync_at' => now(), 'status' => 'active']);

        return back()->with('success', 'Device synced successfully.');
    }

    public function staticQrView(): Response
    {
        $settings = AttendanceSetting::current();

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

        return Inertia::render('Attendance/StaticQr', [
            'token' => $settings->static_qr_token,
            'enabled' => $settings->static_qr_enabled,
            'localIp' => $primaryIp,
            'availableIps' => $ips,
        ]);
    }
}
