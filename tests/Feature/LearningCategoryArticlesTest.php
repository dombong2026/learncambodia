<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\EditorMedia;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LearningCategoryArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LearningCategoryArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_publishes_five_articles_with_images_per_active_category_and_can_be_retried(): void
    {
        Storage::fake('local');
        $this->seed(CategorySeeder::class);
        $category = Category::where('code', 'IELTS')->firstOrFail();
        $inactive = Category::where('code', 'CPE')->firstOrFail();
        $inactive->update(['is_published' => false]);
        $author = User::factory()->create();
        $author->forceFill(['role' => User::ROLE_ADMIN])->save();

        $seeder = app(LearningCategoryArticleSeeder::class);
        $seeder->run($author->id);
        $seeder->run($author->id);

        $this->assertSame(110, ContentEntry::publishedArticles()->count());
        $this->assertSame(5, EditorMedia::count());
        $this->assertFalse(ContentEntry::where('category_id', $inactive->id)->exists());
        $articles = $category->articles()->with('featuredMedia')->get();
        $this->assertCount(5, $articles);

        $page = $this->get(route('categories.show', $category))->assertOk()
            ->assertSee('data-category-short="IELTS"', false)->assertDontSee('data-category-short="CPE"', false);
        foreach ($articles as $article) {
            $url = route('editor-media.show', $article->featuredMedia);
            $page->assertSee($article->title)->assertSee($url);
            $this->get($article->publicUrl())->assertOk()->assertSee($article->title)->assertSee($url);
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        }
        $this->get(route('articles.index', ['category' => $category->slug]))
            ->assertOk()->assertSee($articles->first()->title)->assertDontSee('Give a Clear Safety Instruction in English');

        $category->update(['is_published' => false]);
        $this->get(route('categories.show', $category))->assertNotFound();
        $this->get($articles->first()->publicUrl())->assertNotFound();
    }
}
