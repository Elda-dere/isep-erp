<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_can_be_created(): void
    {
        $category = Category::create(['name' => 'Beverages', 'description' => 'Drinks']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Beverages']);
    }

    public function test_description_is_optional(): void
    {
        $category = Category::create(['name' => 'Snacks']);

        $this->assertNull($category->fresh()->description);
    }
}
