<?php

use App\Models\CourseExam;
use App\Models\EditorMedia;
use App\Models\ExamType;
use App\Models\User;
use App\Support\GuestAssessment;
use Illuminate\Support\Facades\Storage;

$root = realpath(__DIR__.'/../..');

function requireQa(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('QA_GUARD_FAILED: '.$label);
    }
}

// This fixture must never run against a production installation.
requireQa(getenv('GITHUB_ACTIONS') === 'true', 'github_runner');
requireQa(getenv('CI') === 'true', 'ci_environment');
requireQa(getenv('APP_ENV') === 'testing', 'testing_environment');
requireQa(
    realpath(getenv('GITHUB_WORKSPACE') ?: '/nonexistent') === $root,
    'github_workspace'
);
requireQa(
    !str_starts_with($root, '/www/wwwroot/'),
    'not_production_webroot'
);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

requireQa(app()->environment('testing'), 'application_environment');
requireQa(config('database.default') === 'sqlite', 'sqlite_only');

$database = realpath(
    (string) config('database.connections.sqlite.database')
);

requireQa(
    $database === realpath(
        $root.'/database/browser-assessment-qa.sqlite'
    ) && $database !== false,
    'dedicated_qa_database'
);

requireQa(
    config('filesystems.managed') === 'local',
    'local_storage'
);

requireQa(GuestAssessment::enabled(), 'guest_rehearsal_enabled');

$type = ExamType::firstOrCreate([
    'kind' => 'quiz',
    'name' => 'Browser Assessment QA',
]);

$admin = User::factory()->create([
    'role' => 'admin',
    'email' => 'assessment-browser-fixture@example.test',
]);

$defaults = [
    'kind' => 'quiz',
    'exam_type_id' => $type->id,
    'course_id' => null,
    'pricing' => null,
    'published' => true,
    'time_limit' => 5,
    'passing_percent' => 100,
    'attempt_limit' => 1,
    'shuffle' => false,
    'gamified' => false,
    'live_feedback' => false,
    'instructions_html' => '<p>Synthetic browser acceptance.</p>',
];

$specialized = CourseExam::create([
    'title' => 'Browser QA Specialized Assessment',
    'viewer_mode' => 'specialized',
    'guest_enabled' => false,
    'questions' => [[
        'format' => 'choice',
        'prompt' => 'Choose the correct QA answer',
        'a' => 'Correct QA answer',
        'b' => 'Incorrect QA answer',
        'correct' => 'a',
        'points' => 1,
    ]],
] + $defaults);

$wav = 'RIFF'.pack('V', 16036).'WAVEfmt '
    .pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
    .'data'.pack('V', 16000).str_repeat("\0", 16000);

$path = 'editor-media/browser-assessment-qa.wav';

requireQa(
    Storage::disk('local')->put($path, $wav),
    'synthetic_audio_created'
);

$media = EditorMedia::create([
    'context' => 'exam',
    'uploaded_by' => $admin->id,
    'mime' => 'audio/wav',
    'path' => $path,
    'original_name' => 'browser-qa.wav',
    'size' => strlen($wav),
]);

$guest = CourseExam::create([
    'title' => 'Browser QA Native Guest Assessment',
    'viewer_mode' => 'default',
    'guest_enabled' => true,
    'live_feedback' => true,
    'questions' => [[
        'format' => 'listening',
        'prompt' => 'Listen and choose the QA answer',
        'a' => 'Correct listening answer',
        'b' => 'Incorrect listening answer',
        'correct' => 'a',
        'points' => 1,
        'media_id' => $media->id,
        'feedback' => 'Private QA answer explanation',
    ]],
] + $defaults);

$guest->media()->attach($media->id);

requireQa($specialized->isSpecialized(), 'specialized_fixture');
requireQa(!GuestAssessment::allowed($specialized), 'engine_separation');
requireQa(GuestAssessment::allowed($guest), 'guest_fixture');

file_put_contents(
    __DIR__.'/.qa-assessment-fixtures.json',
    json_encode([
        'specialized' => route(
            'public.quizzes.show', $specialized, false
        ),
        'guest' => route(
            'public.quizzes.show', $guest, false
        ),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
);

echo "SPECIALIZED_BROWSER_FIXTURE=READY\n";
echo "NATIVE_GUEST_BROWSER_FIXTURE=READY\n";
echo "PROTECTED_AUDIO_FIXTURE=READY\n";
