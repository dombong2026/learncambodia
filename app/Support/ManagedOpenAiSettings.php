<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ManagedOpenAiSettings
{
    public const FEATURES = [
        'exam' => 'Exams',
        'quiz' => 'Quizzes',
        'test' => 'Placement tests',
        'course' => 'Courses and lessons',
        'audio' => 'Listening audio',
    ];

    public static function saved(): array
    {
        $payload = DB::table('managed_openai_settings')->where('id', 1)->value('payload');

        return $payload === null
            ? ['key' => null, 'features' => []]
            : json_decode(Crypt::decryptString($payload), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function save(array $data): void
    {
        DB::table('managed_openai_settings')->upsert([[
            'id' => 1,
            'payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
            'updated_at' => now(),
        ]], ['id'], ['payload', 'updated_at']);
    }

    public static function enabled(array $settings, string $feature): bool
    {
        return filled($settings['key']) && in_array($feature, $settings['features'], true);
    }
}
