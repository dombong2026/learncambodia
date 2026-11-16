<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\User;

trait PublishesLearningCategoryFixtures
{
    private function publishLearningCategoryFixtures(): void
    {
        $author = User::factory()->create();
        foreach (Category::all() as $category) {
            ContentEntry::create([
                'category_id' => $category->id,
                'type' => 'post', 'status' => 'published', 'published_at' => now(),
                'title' => 'Study guide for '.$category->code,
                'slug' => 'study-guide-for-'.strtolower($category->code),
                'body' => 'A published learning resource.',
                'created_by' => $author->id,
            ]);
        }
    }
}
