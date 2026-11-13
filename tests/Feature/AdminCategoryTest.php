<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_admin_can_create_an_unpublished_category(): void
    {
        $this->actingAs($this->admin())->post('/dashboard/categories', [
            'code' => 'NEW',
            'name' => 'Additional Learning Program',
            'group' => 'english-courses',
            'description' => 'Independent newly designed learning content.',
            'sort_order' => 20,
            'is_published' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['code' => 'NEW', 'is_published' => 0]);
        $this->get('/categories/new-additional-learning-program')->assertNotFound();
    }

    public function test_published_category_can_be_unpublished_by_admin(): void
    {
        $category = Category::create([
            'code' => 'TEST', 'slug' => 'test-test-pathway',
            'name' => 'Test Pathway', 'group' => 'english-courses',
            'description' => 'Content for an acceptance test.',
            'sort_order' => 5, 'is_published' => true,
        ]);

        $this->actingAs($this->admin())->patch('/dashboard/categories/'.$category->slug, [
            'code' => 'TEST', 'name' => 'Test Pathway',
            'group' => 'english-courses',
            'description' => 'Updated acceptance test content.',
            'sort_order' => 5, 'is_published' => '0',
        ])->assertRedirect();

        $this->assertFalse($category->fresh()->is_published);
        $this->get('/categories/test-test-pathway')->assertNotFound();
    }

    public function test_student_cannot_create_or_change_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/dashboard/categories', [
                'code' => 'HACKED',
                'name' => 'Unsafe Category',
            ])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['code' => 'HACKED']);
    }
}
