<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\CategoryVisual;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FrontendExperienceRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_art_is_meaningful_and_safe_for_unknown_codes(): void
    {
        $this->assertSame('book-open', CategoryVisual::iconFor(new Category([
            'code' => 'GE', 'group' => 'english-courses',
        ])));
        $this->assertSame('clipboard-check', CategoryVisual::iconFor(new Category([
            'code' => 'IELTS', 'group' => 'exam-preparation',
        ])));
        $this->assertSame('graduation', CategoryVisual::iconFor(new Category([
            'code' => 'TEFL', 'group' => 'teacher-development',
        ])));
        $this->assertSame('briefcase', CategoryVisual::iconFor(new Category([
            'code' => 'ESP', 'group' => 'specialist-english',
        ])));
        $this->assertSame('award', CategoryVisual::iconFor(new Category([
            'code' => 'CEFR', 'group' => 'frameworks',
        ])));
        $this->assertSame('school', CategoryVisual::iconFor(new Category([
            'code' => 'UNKNOWN', 'group' => 'teacher-development',
        ])));
        $this->assertSame('book', CategoryVisual::groupIcon('unknown-group'));
    }

    public function test_category_search_is_preserved_when_switching_group_filters(): void
    {
        $this->seed(CategorySeeder::class);

        $this->get('/categories?q=IELTS&group=exam-preparation')
            ->assertOk()
            ->assertSee('id="category-directory"', false)
            ->assertSee('value="IELTS"', false)
            ->assertSee(
                'href="'.e(route('categories.index', [
                    'q' => 'IELTS', 'group' => 'english-courses',
                ])).'#category-directory"',
                false
            )
            ->assertSee(
                'href="'.e(route('categories.index', [
                    'q' => 'IELTS',
                ])).'#category-directory"',
                false
            )
            ->assertSee('aria-current="page"', false)
            ->assertSee(__('longpage.clear_filters'))
            ->assertSee('role="search"', false);
    }

    public function test_category_filtering_and_footer_links_are_grounded_in_specific_searches(): void
    {
        $this->seed(CategorySeeder::class);

        $response = $this->get('/categories?lang=en')->assertOk();
        $response->assertSee('premium-corner-icon', false);
        $response->assertSee(
            'href="'.e(route('categories.index', ['q' => 'TOEFL'])).'"',
            false
        );
        $response->assertSee(
            'href="'.e(route('categories.index', ['q' => 'TOEIC'])).'"',
            false
        );
        $response->assertSee(
            'href="'.e(route('categories.index', ['q' => 'DET'])).'"',
            false
        );
        $response->assertDontSee(
            'href="'.e(route('categories.index', ['group' => 'exam-preparation'])).'">TOEFL &amp; TOEIC</a>',
            false
        );

        $this->get('/categories?q=TOEFL')->assertOk()
            ->assertSee('Test of English as a Foreign Language');
        $this->get('/categories?q=TOEIC')->assertOk()
            ->assertSee('Test of English for International Communication');
    }

    public function test_empty_catalog_uses_compact_status_without_unapproved_courses_or_people(): void
    {
        $this->seed(CategorySeeder::class);
        $category = Category::query()->where('code', 'IELTS')->firstOrFail();

        $this->get('/categories/'.$category->slug)->assertOk()
            ->assertSee('premium-card', false)
            ->assertSee('border-dashed', false)
            ->assertSee(__('longpage.no_approved_courses'))
            ->assertDontSee('Secret private lesson');

        $this->get('/courses')->assertOk()
            ->assertSee(__('frontend.no_courses'))
            ->assertSee('border-dashed', false);

        $this->get('/')->assertOk()
            ->assertSee('Learn Cambodia home')
            ->assertDontSee('Secret private lesson');
    }

    public function test_category_group_unit_is_singular_or_plural_in_each_locale(): void
    {
        $this->seed(CategorySeeder::class);
        foreach (['en', 'km', 'zh'] as $locale) {
            $this->get('/categories?lang='.$locale)->assertOk()
                ->assertDontSee('longpage.category_one')
                ->assertDontSee('longpage.category_many');
        }
    }
    public function test_learning_focus_cards_center_large_icons_titles_and_real_group_counts(): void
    {
        $this->seed(CategorySeeder::class);
        $response = $this->get('/categories?lang=en')->assertOk()
            ->assertSee('data-learning-focus-card', false)
            ->assertSee('data-learning-focus-icon', false)
            ->assertSee('data-learning-focus-name', false)
            ->assertSee('data-learning-focus-count', false)
            ->assertSee('learning-focus-card', false)
            ->assertSee('h-20 w-20', false);

        $this->assertSame(5, substr_count($response->getContent(), 'data-learning-focus-card'));
        $this->assertSame(5, substr_count($response->getContent(), 'data-learning-focus-count'));
        $this->assertSame(5, substr_count($response->getContent(), 'data-learning-focus-icon'));
        $this->assertSame(5, substr_count($response->getContent(), 'data-learning-focus-name'));

        // Each group remains a single link; counts still reflect the seeded database.
        $this->assertMatchesRegularExpression('/4 Categories/', $response->getContent());
        $this->assertMatchesRegularExpression('/1 Category/', $response->getContent());
        $this->get('/categories?q=IELTS&lang=en')->assertOk()
            ->assertSee('q=IELTS', false);
    }

}
