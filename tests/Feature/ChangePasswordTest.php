<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => 'old-password-123']);
    }

    public function test_page_renders_for_a_signed_in_user(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user())
            ->get('/account/password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Password'));
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/account/password')->assertRedirect('/login');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'not-the-password',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_short_new_password_is_rejected(): void
    {
        $this->actingAs($this->user())->put('/account/password', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $this->actingAs($this->user())->put('/account/password', [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'something-different-1',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_can_be_changed(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertSessionHasNoErrors();

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check('brand-new-password-1', $fresh->password));
        $this->assertFalse(Hash::check('old-password-123', $fresh->password));
    }
}