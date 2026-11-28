<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class AssessmentQuestions
{
    public const FORMATS = [
        'choice' => 'Multiple choice', 'reading' => 'Reading comprehension', 'writing' => 'Writing',
        'boolean' => 'Yes / No', 'order' => 'Drag and drop ordering', 'likert' => 'Likert scale',
        'listening' => 'Listening comprehension', 'speaking' => 'Speaking recording',
        'fill' => 'Fill in the blank', 'short' => 'Short answer',
    ];

    public const CHOICES = ['choice', 'reading', 'listening'];

    public static function manual(array $question): bool
    {
        return in_array($question['format'] ?? 'choice', ['writing', 'speaking'], true);
    }

    public static function scored(array $question): bool
    {
        return ($question['format'] ?? 'choice') !== 'likert';
    }

    public static function validate(array $questions): array
    {
        foreach ($questions as $index => &$question) {
            $format = $question['format'] ?? 'choice';
            $correct = $question['correct'] ?? '';
            $options = array_keys(array_filter(array_intersect_key($question, array_flip(['a','b','c','d'])), 'filled'));
            $message = match (true) {
                in_array($format, self::CHOICES, true) && (!in_array('a', $options) || !in_array('b', $options) || !in_array($correct, $options, true)) => 'Enter at least options A and B and the letter of the correct option.',
                $format === 'boolean' && !in_array($correct, ['true','false'], true) => 'Enter true for Yes or false for No.',
                $format === 'order' && (count($options) < 2 || count(explode(',', $correct)) !== count($options)
                    || array_diff($options, array_map('trim', explode(',', $correct))) !== []) => 'Enter each populated item once in the correct order, separated by commas.',
                in_array($format, ['short', 'fill'], true) && blank($correct) => 'Enter an accepted answer.',
                $format === 'reading' && blank($question['passage'] ?? null) => 'Add a reading passage.',
                $format === 'listening' && empty($question['media_id']) => 'Upload listening audio.',
                default => null,
            };
            if ($message) {
                throw ValidationException::withMessages(["questions.$index.correct" => $message]);
            }
            $question['format'] = $format;
            if ($format !== 'listening') {
                unset($question['media_id']);
            }
            if ($format !== 'reading') {
                unset($question['passage']);
            }
            if (self::manual($question) || !self::scored($question)) {
                unset($question['correct']);
            }
        }

        return $questions;
    }

    public static function correct(array $question, string $answer): bool
    {
        $key = $question['correct'] ?? '';
        $format = $question['format'] ?? 'choice';
        if (in_array($format, ['short','fill'], true)) {
            $normalize = static fn (string $text): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
            return $normalize($answer) !== '' && in_array($normalize($answer), array_map($normalize, explode('|', $key)), true);
        }

        return $format === 'order' ? str_replace(' ', '', $key) === $answer : $answer !== '' && $key === $answer;
    }

    public static function result(array $snapshot, array $answers, array $awards = []): array
    {
        $earned = $available = 0;
        $pending = false;
        foreach ($snapshot['questions'] as $i => $question) {
            if (!self::scored($question)) {
                continue;
            }
            $available += $question['points'];
            if (self::manual($question)) {
                $pending = $pending || !array_key_exists($i, $awards);
                $earned += $awards[$i]['points'] ?? 0;
            } elseif (self::correct($question, $answers[$i] ?? '')) {
                $earned += $question['points'];
            }
        }
        $score = $pending || !$available ? null : intdiv($earned * 100, $available);

        return ['score' => $score, 'passed' => $score !== null && $score >= $snapshot['passing_percent']];
    }

    public static function reviewStatus(array $question, string $answer, ?array $award): string
    {
        if (blank($answer) && $award === null) {
            return 'unanswered';
        }
        if (!self::scored($question)) {
            return 'unscored';
        }
        if (!self::manual($question)) {
            return self::correct($question, $answer) ? 'correct' : 'incorrect';
        }
        if ($award === null) {
            return 'pending';
        }

        return match (true) {
            $award['points'] >= $question['points'] => 'correct',
            $award['points'] > 0 => 'partial',
            default => 'incorrect',
        };
    }

    public static function aiListeningMedia(array $questions): array
    {
        $ids = array_filter(array_column($questions, 'media_id'));

        return $ids === [] ? [] : \App\Models\EditorMedia::whereIn('id', $ids)
            ->where('original_name', 'openai-listening.wav')->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    public static function mediaIds(array $snapshot): array
    {
        return array_values(array_unique([
            ...EditorHtml::sourceIds($snapshot['instructions_html'] ?? null, lesson: true),
            ...array_map('intval', array_filter(array_column($snapshot['questions'], 'media_id'))),
        ]));
    }
}
