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

Artisan::command('quizzes:publish-practice', function () {
    $count = app(\Database\Seeders\StarterQuizSeeder::class)->publishPractice();
    $this->info("Public practice quizzes: $count");
})->purpose('Publish the ten selected free English practice quizzes with two attempts each');

Artisan::command('exams:publish-specialized-practice', function () {
    $exams = app(\Database\Seeders\SpecializedPracticeExamSeeder::class)->run();
    $this->info("Specialized practice exams: {$exams->count()}");
    foreach ($exams as $exam) {
        $this->line($exam->title.' | '.$exam->publicUrl());
    }
})->purpose('Publish ten standalone English practice exams with 50 questions and a 120-minute timer');
