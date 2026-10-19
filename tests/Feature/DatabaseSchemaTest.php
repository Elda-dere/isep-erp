<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_a_tables_exist(): void
    {
        $tables = [
            'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
            'audit_logs', 'departments', 'positions', 'employees', 'qr_codes',
            'attendance_records', 'attendance_events', 'approved_networks',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_laravel_default_extra_tables_are_absent(): void
    {
        $extras = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'password_reset_tokens'];

        foreach ($extras as $table) {
            $this->assertFalse(Schema::hasTable($table), "Unexpected table: {$table}");
        }
    }

    public function test_users_table_matches_the_srs_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'remember_token'));
        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertTrue(Schema::hasColumns('users', ['name', 'email', 'password', 'is_active']));
    }
}