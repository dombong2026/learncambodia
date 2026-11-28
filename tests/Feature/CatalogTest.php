<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublishesLearningCategoryFixtures;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;
    use PublishesLearningCategoryFixtures;

    public function test_homepage_displays_published_pathways(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();

        $this->get('/?lang=en')->assertOk()
            ->assertSee(trans('pages.home_hero_title', [], 'en'))
            ->assertSee('id="home-title"', false)
            ->assertSee('IELTS');
    }

    public function test_header_tray_lists_individual_published_categories(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();
        foreach (['/', route('navigation.categories')] as $url) {
            $response = $this->get($url)->assertOk();

            foreach (['ESL', 'GE', 'EAP', 'TOEIC', 'TOEFL', 'IELTS'] as $code) {
                $response->assertSee(
                    'data-category-short="'.$code.'"', false
                );
            }

            foreach (['EFL', 'CELPIP', 'TEYL'] as $code) {
                $response->assertDontSee(
                    'data-category-short="'.$code.'"', false
                );
            }
        }
    }

    public function test_search_returns_matching_category(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();

        $this->get('/categories?q=IELTS')->assertOk()
            ->assertSee('International English Language Testing System');
    }

    public function test_empty_category_is_hidden_even_when_marked_published(): void
    {
        $this->seed(CategorySeeder::class);
        $category = Category::where('code', 'ESL')->firstOrFail();
        $this->get('/')->assertOk()->assertDontSee('data-category-short="ESL"', false);
        $this->get('/categories?q=ESL')->assertOk()->assertDontSee($category->name);
        $this->get(route('categories.show', $category))->assertNotFound();
    }

    public function test_unpublished_category_is_not_public(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();
        $category = Category::query()->where('code', 'IELTS')->firstOrFail();
        $category->update(['is_published' => false]);

        $this->get('/categories/'.$category->slug)->assertNotFound();
    }

    public function test_reseeding_does_not_republish_a_withdrawn_category(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();
        $category = Category::query()->where('code', 'IELTS')->firstOrFail();
        $category->update(['is_published' => false]);

        $this->seed(CategorySeeder::class);

        $this->assertFalse($category->fresh()->is_published);
        $this->get('/categories/'.$category->slug)->assertNotFound();
    }

    public function test_cefr_reference_lists_all_six_levels(): void
    {
        $this->seed(CefrLevelSeeder::class);

        $this->get('/cefr')->assertOk()->assertSee('A1')->assertSee('C2');
    }
}
