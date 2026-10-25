<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FlashPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_success_message_reaches_the_page(): void
    {
        $this->withoutVite();

        $this->withSession(['success' => 'Product created.'])
            ->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', 'Product created.'));
    }

    public function test_there_is_no_message_by_default(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', null));
    }
}