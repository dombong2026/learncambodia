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
