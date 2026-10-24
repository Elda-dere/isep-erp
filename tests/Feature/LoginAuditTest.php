<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-1']);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-1']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_failed_login_is_logged_without_the_typed_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-1']);

        $this->post('/login', ['email' => $user->email, 'password' => 'typed-wrong-password']);

        $row = AuditLog::where('action', 'auth.login_failed')->firstOrFail();

        $this->assertNull($row->user_id);
        $this->assertSame('wrong_password', $row->new_values['reason']);
        $this->assertSame($user->email, $row->new_values['email']);
        $this->assertStringNotContainsString(
            'typed-wrong-password',
            json_encode(AuditLog::all()->toArray())
        );
    }

    public function test_unknown_email_and_inactive_account_have_their_own_reasons(): void
    {
        $inactive = User::factory()->inactive()->create(['password' => 'correct-password-1']);

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever-123']);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'correct-password-1']);

        $reasons = AuditLog::where('action', 'auth.login_failed')
            ->orderBy('id')->get()->pluck('new_values.reason')->all();

        $this->assertSame(['unknown_email', 'inactive_account'], $reasons);
    }

    public function test_the_ip_address_is_stored(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-1']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->post('/login', ['email' => $user->email, 'password' => 'correct-password-1']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'ip_address' => '10.1.2.3']);
    }

    public function test_a_password_change_is_logged(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed', 'user_id' => $user->id]);
    }
}