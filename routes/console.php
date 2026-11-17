<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('editorial:publish-learning-articles {author : Administrator user ID}', function (string $author) {
    $seeder = app(\Database\Seeders\LearningCategoryArticleSeeder::class);
    $seeder->setCommand($this);
    $seeder->run((int) $author);
})->purpose('Publish five English reading articles per covered active learning category');

Artisan::command('courses:import-starter {author : Administrator user ID}', function (string $author) {
    $seeder = app(\Database\Seeders\StarterCourseSeeder::class);
    $seeder->setCommand($this);
    $seeder->run((int) $author);
})->purpose('Create 20 draft courses with lessons and featured images');

Artisan::command('quizzes:import-starter {author? : Administrator user ID for the Media Library}', function (?string $author = null) {
    if ($author !== null && (!ctype_digit($author) || (int) $author < 1)) {
        $this->error('Enter a valid administrator user ID.');
        return 1;
    }

    $count = app(\Database\Seeders\StarterQuizSeeder::class)->run($author === null ? null : (int) $author);
    $this->info("New draft quizzes: $count");
    $this->line('Starter bank: 50 quizzes, 20 questions each, 50 featured covers');

    return 0;
})->purpose('Import or verify the 50 starter English quiz drafts');
