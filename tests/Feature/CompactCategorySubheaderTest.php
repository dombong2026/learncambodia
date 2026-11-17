<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublishesLearningCategoryFixtures;
use Tests\TestCase;

final class CompactCategorySubheaderTest extends TestCase
{
    use RefreshDatabase;
    use PublishesLearningCategoryFixtures;

    public function test_public_category_tray_uses_codes_but_preserves_full_names_and_destinations(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();

        foreach (['/?lang=en', '/categories?lang=en', '/cefr?lang=en'] as $url) {
            $html = $this->get($url)->assertOk()
                ->assertSee('data-category-tray', false)
                ->assertSee('data-category-short="ESL"', false)
                ->assertSee('data-category-short="EFL"', false)
                ->assertSee('data-category-short="EAP"', false)
                ->getContent();

            preg_match_all(
                '/<a\b[^>]*data-category-short="([^"]+)"[^>]*title="([^"]+)"[^>]*aria-label="([^"]+)"[^>]*href="([^"]+)"[^>]*>([^<]*)<\/a>/u',
                $html,
                $matches,
                PREG_SET_ORDER
            );

            $this->assertCount(23, $matches);
            foreach ($matches as $match) {
                $code = $match[1];
                $category = Category::where('code', $code)->firstOrFail();

                $this->assertSame($category->name, html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5));
                $this->assertSame($code.' — '.$category->name, html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5));
                $this->assertSame(route('categories.show', $category), html_entity_decode($match[4], ENT_QUOTES | ENT_HTML5));
                $this->assertSame($code, trim($match[5]), 'Only the abbreviation should be visible inside each category link.');
            }
        }
    }

    public function test_category_visibility_changes_reach_both_navigation_responses_immediately(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();
        $category = Category::where('code', 'ESL')->firstOrFail();
        $default = $category->only(['code', 'name', 'group', 'description', 'sort_order']);
        $change = route('admin.categories.update', $category);
        $edit = route('admin.categories.edit', $category);
        $feed = route('navigation.categories');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get('/?lang=en')->assertOk()->assertSee('data-category-short="ESL"', false);
        $this->get($feed)->assertOk()->assertSee('data-category-short="ESL"', false)
            ->assertHeader('X-Category-Navigation', '1');

        $this->actingAs($admin)->from($edit)->patch($change, $default + ['is_published' => 0])
            ->assertRedirect($edit)->assertSessionHas('category_navigation_changed', true);
        $this->get($edit)->assertOk()->assertSee('data-category-navigation-changed', false);
        $this->get('/cefr?lang=en')->assertOk()->assertDontSee('data-category-short="ESL"', false)
            ->assertSee('data-category-short="EFL"', false);
        $fragment = $this->get($feed)->assertOk()->assertDontSee('data-category-short="ESL"', false);
        $this->assertStringContainsString('no-store', $fragment->headers->get('Cache-Control'));
        $this->get(route('categories.show', $category))->assertNotFound();

        $this->from($edit)->patch($change, $default + ['is_published' => 1])
            ->assertRedirect($edit)->assertSessionHas('category_navigation_changed', true);
        $this->get('/?lang=en')->assertOk()->assertSee('data-category-short="ESL"', false);
        $this->get($feed)->assertOk()->assertSee('data-category-short="ESL"', false);
    }

    public function test_category_pages_and_search_still_show_the_full_name(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLearningCategoryFixtures();
        $category = Category::where('code', 'ESL')->firstOrFail();

        $this->get(route('categories.show', $category))->assertOk()
            ->assertSee('English as a Second Language');
        $this->get('/categories?q=English+as+a+Second+Language')->assertOk()
            ->assertSee('English as a Second Language');
    }

    public function test_account_workspace_navigation_is_unmodified_by_public_subheader(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'student'])->save();

        $this->actingAs($user)->get('/account?lang=en')->assertOk()
            ->assertDontSee('data-category-tray', false);
    }
}
