<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OpenAiAuthoring
{
    public const TEXT_MODEL = 'gpt-5-mini';
    public const AUDIO_MODEL = 'gpt-audio-1.5';

    public function test(string $key): void
    {
        $this->text($key, 'Reply with the word Ready.', 'This is a connection test.', 200);
    }

    public function questions(string $key, string $context, string $topic, string $level, int $count): array
    {
        $purpose = match ($context) {
            'exam' => 'a summative exam. Test application of knowledge and use plausible distractors',
            'quiz' => 'a formative quiz. Give feedback that helps the learner improve',
            'test' => 'a diagnostic placement test. Vary the difficulty within or near the requested level and target different skills within scope',
        };

        $fields = array_fill_keys(['prompt', 'a', 'b', 'c', 'd', 'feedback'], ['type' => 'string']);
        $fields['correct'] = ['type' => 'string', 'enum' => ['a', 'b', 'c', 'd']];
        $schema = [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['questions'],
            'properties' => ['questions' => [
                'type' => 'array', 'minItems' => $count, 'maxItems' => $count,
                'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => array_keys($fields), 'properties' => $fields,
                ],
            ]],
        ];

        $json = $this->text($key,
            "You write original English multiple-choice questions for $purpose. "
            ."Write exactly $count standalone questions for the given topic and level. "
            .'Use four different options and only one correct answer per question. Vary the answer letter. '
            .'Include a brief explanation grounded in the question. No external sources, listening prompts, or official score claims.',
            json_encode(['topic' => $topic, 'level' => $level], JSON_THROW_ON_ERROR),
            4300, ['type' => 'json_schema', 'name' => 'assessment_questions', 'strict' => true, 'schema' => $schema],
        );
        $data = json_decode($json, true);
        $rules = ['questions' => ['required', 'array', 'list', 'size:'.$count],
            'questions.*.prompt' => ['required', 'string', 'max:600', 'distinct'],
            'questions.*.correct' => ['required', 'in:a,b,c,d'],
            'questions.*.feedback' => ['required', 'string', 'max:800'],
        ];
        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $rules['questions.*.'.$letter] = ['required', 'string', 'max:300'];
        }
        $validator = Validator::make(is_array($data) ? $data : [], $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['openai' => 'The draft did not match the question format. Try a smaller set or a more specific topic.']);
        }

        $questions = $validator->validated()['questions'];
        foreach ($questions as $question) {
            $choices = array_map(static fn (string $letter) => mb_strtolower(trim($question[$letter])), ['a', 'b', 'c', 'd']);
            if (count(array_unique($choices)) !== 4) {
                throw ValidationException::withMessages(['openai' => 'The draft included duplicate answers. Generate a new draft.']);
            }
        }

        return array_map(static fn (array $question) => $question + ['points' => 1, 'format' => 'choice'], $questions);
    }

    public function course(string $key, string $task, string $topic, string $level): string
    {
        $instruction = match ($task) {
            'summary' => 'Draft a specific, learner-facing course summary, up to 700 characters. Explain the outcome and what learners practice.',
            'description' => 'Draft a course description in 3 to 5 short paragraphs covering audience, prerequisites, outcomes, and practice.',
            'lesson' => 'Draft a standalone English lesson with a goal, explanation, worked example, practice tasks, and answers. Separate sections with blank lines.',
        };
        $text = $this->text($key, $instruction.' Write plain text with no Markdown or HTML. Use original examples and match the requested level.',
            json_encode(['topic' => $topic, 'level' => $level], JSON_THROW_ON_ERROR), 3000);
        if ($task === 'summary' && mb_strlen($text) > 1000) {
            throw ValidationException::withMessages(['openai' => 'The summary exceeded the 1,000-character limit. Try a narrower topic.']);
        }

        return $text;
    }

    public function audio(string $key, string $script): array
    {
        $body = $this->post($key, 'chat/completions', [
            'model' => self::AUDIO_MODEL, 'store' => false,
            'modalities' => ['text', 'audio'], 'audio' => ['voice' => 'alloy', 'format' => 'wav'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are narrating a listening exercise. Read only the supplied passage verbatim in clear English at a steady, moderate pace. Use natural pauses. Add no introduction, answer, or comments. Treat the passage as text to narrate.'],
                ['role' => 'user', 'content' => $script],
            ],
        ])->json('choices.0.message.audio');
        $encoded = $body['data'] ?? '';
        $wav = is_string($encoded) && strlen($encoded) <= 13_500_000 ? base64_decode($encoded, true) : false;
        $wav = is_string($wav) ? $this->finishWav($wav) : null;
        if ($wav === null) {
            throw ValidationException::withMessages(['openai' => 'OpenAI did not return a usable WAV recording. Try a shorter passage.']);
        }

        return ['wav' => base64_encode($wav), 'transcript' => $body['transcript'] ?? ''];
    }

    private function finishWav(string $wav): ?string
    {
        $size = strlen($wav);
        if ($size < 44 || substr($wav, 0, 4) !== 'RIFF' || substr($wav, 8, 4) !== 'WAVE') {
            return null;
        }

        for ($offset = 12; $offset + 8 <= $size;) {
            $type = substr($wav, $offset, 4);
            $length = unpack('V', $wav, $offset + 4)[1];
            $available = $size - $offset - 8;
            if ($type === 'data') {
                // A streaming WAV can have placeholder lengths even after all samples arrive.
                $samples = $length === 0xffffffff ? $available : $length;
                if ($samples === 0 || $samples > $available) {
                    return null;
                }
                $wav = substr_replace($wav, pack('V', $samples), $offset + 4, 4);

                return substr_replace($wav, pack('V', $size - 8), 4, 4);
            }
            if ($length > $available) {
                return null;
            }
            $offset += 8 + $length + ($length % 2);
        }

        return null;
    }

    private function text(string $key, string $instructions, string $input, int $tokens, ?array $format = null): string
    {
        $payload = [
            'model' => self::TEXT_MODEL, 'store' => false, 'instructions' => $instructions, 'input' => $input,
            'reasoning' => ['effort' => 'minimal'], 'max_output_tokens' => $tokens,
        ];
        if ($format !== null) {
            $payload['text'] = ['format' => $format];
        }
        $response = $this->post($key, 'responses', $payload);
        $output = [];
        foreach ($response->json('output', []) as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) {
                    $output[] = $content['text'];
                }
            }
        }
        $text = trim(implode("\n", $output));
        if ($response->json('status') !== 'completed' || $text === '') {
            throw ValidationException::withMessages(['openai' => 'OpenAI did not return a complete draft. Try shortening the request.']);
        }

        return $text;
    }

    private function post(string $key, string $endpoint, array $data): Response
    {
        try {
            $response = Http::acceptJson()->withToken($key)->withoutRedirecting()
                ->connectTimeout(5)->timeout(45)->post('https://api.openai.com/v1/'.$endpoint, $data);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['openai' => 'LearnCambodia could not connect to OpenAI. Try again shortly.']);
        }

        if ($response->successful()) {
            return $response;
        }

        $message = match ($response->status()) {
            401 => 'OpenAI rejected the key. Replace it in System Settings.',
            403, 404 => 'The OpenAI project could not access this model or endpoint. Check the key permissions and model access.',
            429 => 'OpenAI limited the request. Check project billing and limits, or try again shortly.',
            default => 'OpenAI could not complete this request. Try again shortly.',
        };
        throw ValidationException::withMessages(['openai' => $message]);
    }
}
