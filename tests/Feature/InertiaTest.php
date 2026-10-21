<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaTest extends TestCase
{
    public function test_hello_page_is_rendered_by_inertia_with_props(): void
    {
        // The test does not build the front end, so skip loading the Vite files.
        $this->withoutVite();

        $this->get('/hello')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hello')
                ->where('message', 'This text came from Laravel and was drawn by Vue.')
            );
    }
}