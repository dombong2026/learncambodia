<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompactCategorySubheaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_category_tray_uses_codes_but_preserves_full_names_and_destinations(): void
    {
        $this->seed(CategorySeeder::class);

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

    public function test_category_pages_and_search_still_show_the_full_name(): void
    {
        $this->seed(CategorySeeder::class);
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
