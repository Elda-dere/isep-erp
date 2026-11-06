<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePurgeTest extends TestCase
{
    use RefreshDatabase;

    private int $codes = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'UTC'));
    }

    private function employee(): Employee
    {
        $department = Department::firstOrCreate(['name' => 'Operations']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'title' => 'Clerk']);

        return Employee::firstOrCreate(['employee_number' => 'T-1'], [
            'position_id' => $position->id,
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => 'active',
        ]);
    }

    private function code(string $expires, ?string $revoked = null): QrCode
    {
        $this->codes++;

        return QrCode::create([
            'employee_id' => $this->employee()->id,
            'token_hash' => hash('sha256', 'code-'.$this->codes),
            'expires_at' => Carbon::parse($expires, 'UTC'),
            'revoked_at' => $revoked === null ? null : Carbon::parse($revoked, 'UTC'),
            'created_at' => now(),
        ]);
    }

    public function test_old_used_and_old_expired_codes_are_deleted_and_the_rest_are_kept(): void
    {
        $oldUsed = $this->code('2026-09-20 10:01:00', '2026-09-20 10:00:30');
        $oldExpired = $this->code('2026-09-20 10:01:00');

        $recentUsed = $this->code('2026-10-29 10:01:00', '2026-10-29 10:00:30');
        $recentExpired = $this->code('2026-10-28 10:01:00');
        $active = $this->code('2026-10-30 12:00:30');

        $this->artisan('attendance:purge-qr-codes')->assertSuccessful();

        $this->assertNull(QrCode::find($oldUsed->id));
        $this->assertNull(QrCode::find($oldExpired->id));
        $this->assertNotNull(QrCode::find($recentUsed->id));
        $this->assertNotNull(QrCode::find($recentExpired->id));
        $this->assertNotNull(QrCode::find($active->id));
    }

    public function test_the_days_option_changes_how_long_codes_are_kept(): void
    {
        $twoDaysOld = $this->code('2026-10-28 10:01:00');

        $this->artisan('attendance:purge-qr-codes')->assertSuccessful();
        $this->assertNotNull(QrCode::find($twoDaysOld->id));

        $this->artisan('attendance:purge-qr-codes --days=1')->assertSuccessful();
        $this->assertNull(QrCode::find($twoDaysOld->id));
    }

    public function test_codes_are_always_kept_for_at_least_one_day(): void
    {
        $justUsed = $this->code('2026-10-30 11:59:30', '2026-10-30 11:59:00');
        $yesterday = $this->code('2026-10-28 10:01:00');

        $this->artisan('attendance:purge-qr-codes --days=0')->assertSuccessful();

        $this->assertNotNull(QrCode::find($justUsed->id));
        $this->assertNull(QrCode::find($yesterday->id));
    }

    public function test_the_retention_setting_is_used_by_default(): void
    {
        config(['attendance.qr_retention_days' => 10]);

        $twelveDaysOld = $this->code('2026-10-18 10:01:00');
        $fiveDaysOld = $this->code('2026-10-25 10:01:00');

        $this->artisan('attendance:purge-qr-codes')->assertSuccessful();

        $this->assertNull(QrCode::find($twelveDaysOld->id));
        $this->assertNotNull(QrCode::find($fiveDaysOld->id));
    }

    public function test_a_purge_that_deletes_something_is_audited_without_a_user(): void
    {
        $this->code('2026-09-20 10:01:00');
        $this->code('2026-09-21 10:01:00');

        $this->artisan('attendance:purge-qr-codes')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.qr_codes_purged', 'user_id' => null]);

        $row = \App\Models\AuditLog::where('action', 'attendance.qr_codes_purged')->firstOrFail();

        $this->assertEquals(['qr_codes_deleted' => 2, 'older_than_days' => 30], $row->new_values);
    }

    public function test_a_purge_that_deletes_nothing_writes_no_audit_row(): void
    {
        $this->code('2026-10-30 12:00:30');

        $this->artisan('attendance:purge-qr-codes')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'attendance.qr_codes_purged']);
    }

    public function test_the_purge_is_scheduled_to_run_every_night(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('attendance:purge-qr-codes')
            ->assertSuccessful();
    }
}