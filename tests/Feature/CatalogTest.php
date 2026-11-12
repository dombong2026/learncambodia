<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_displays_published_pathways(): void
    {
        $this->seed(CategorySeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Explore English learning for every ambition.')
            ->assertSee('IELTS');
    }

    public function test_search_returns_matching_category(): void
    {
        $this->seed(CategorySeeder::class);

        $this->get('/categories?q=IELTS')->assertOk()
            ->assertSee('International English Language Testing System');
    }

    public function test_unpublished_category_is_not_public(): void
    {
        $this->seed(CategorySeeder::class);
        $category = Category::query()->where('code', 'IELTS')->firstOrFail();
        $category->update(['is_published' => false]);

        $this->get('/categories/'.$category->slug)->assertNotFound();
    }

    public function test_cefr_reference_lists_all_six_levels(): void
    {
        $this->seed(CefrLevelSeeder::class);

        $this->get('/cefr')->assertOk()->assertSee('A1')->assertSee('C2');
    }
}
