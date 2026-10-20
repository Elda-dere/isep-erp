<?php

namespace Tests\Feature\Catalogue;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_unit_can_be_created(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'symbol' => 'kg']);
    }
}
