<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\ManagedFiles;
use App\Support\PromoPlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminPromoPlayerController extends Controller
{
    private const AUDIO_TYPES = [
        'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg',
    ];

    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('promo', [
            'revision' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:90'],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'volume' => ['required', 'integer', 'between:0,100'],
            'loop' => ['sometimes', 'boolean'],
            'homepage' => ['sometimes', 'boolean'],
            'sidebar' => ['sometimes', 'boolean'],
            'background' => ['nullable', 'file', 'max:5120', 'mimetypes:'.implode(',', array_keys(self::IMAGE_TYPES)), 'dimensions:max_width=3840,max_height=3840'],
            'remove_background' => ['sometimes', 'boolean'],
            'link_label' => ['nullable', 'required_with:link_url', 'string', 'max:40'],
            'link_url' => ['nullable', 'required_with:link_label', 'string', 'max:500', 'regex:~^(?:/(?![/\\\\])[^\\\\\x00-\x20]*|https://[^\\\\\x00-\x20]+)$~iD'],
            'tracks' => ['sometimes', 'array', 'max:8'],
            'tracks.*' => ['array:id,title,artist,transcript,audio,transcript_cues'],
            'tracks.*.id' => ['nullable', 'uuid', 'distinct'],
            'tracks.*.title' => ['nullable', 'string', 'max:100'],
            'tracks.*.artist' => ['nullable', 'string', 'max:100'],
            'tracks.*.transcript' => ['nullable', 'string', 'max:4000'],
            'tracks.*.transcript_cues' => ['sometimes', 'array', 'list', 'min:1', 'max:100'],
            'tracks.*.transcript_cues.*' => ['required', 'array:start,text'],
            'tracks.*.transcript_cues.*.start' => ['required', 'numeric', 'between:0,86400'],
            'tracks.*.transcript_cues.*.text' => ['required', 'string', 'max:4000'],
            'tracks.*.audio' => ['nullable', 'file', 'max:20480', 'mimetypes:'.implode(',', array_keys(self::AUDIO_TYPES))],
        ]);

        $configuration = [
            'title' => $data['title'],
            'accent' => $data['accent'],
            'volume' => (int) $data['volume'],
            'loop' => $request->boolean('loop'),
            'homepage' => $request->boolean('homepage'),
            'sidebar' => $request->boolean('sidebar'),
            'link_label' => $data['link_label'] ?? null,
            'link_url' => $data['link_url'] ?? null,
        ];
        $created = [];

        try {
            $retired = DB::transaction(function () use ($data, $configuration, $request, &$created): array {
                $current = PromoPlayer::settings(lock: true);
                if ($current['revision'] !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['revision' => 'These settings changed in another tab. Reload before saving.'])->errorBag('promo');
                }

                $existing = array_column($current['tracks'], null, 'id');
                $configuration['tracks'] = [];
                foreach ($data['tracks'] ?? [] as $index => $track) {
                    $file = $track['audio'] ?? null;
                    $saved = $existing[$track['id'] ?? ''] ?? null;
                    if (!$saved && !$file && empty($track['title']) && empty($track['artist']) && empty($track['transcript'])) {
                        continue;
                    }
                    if (empty($track['title']) || (!$saved && !$file)) {
                        throw ValidationException::withMessages(["tracks.$index.title" => 'Enter a track title and provide an audio file.'])->errorBag('promo');
                    }
                    $transcript = $track['transcript'] ?? null;
                    $cues = $track['transcript_cues'] ?? (
                        !$file && $this->normalizeTranscript($transcript) === $this->normalizeTranscript($saved['transcript'] ?? null)
                            ? ($saved['transcript_cues'] ?? [])
                            : []
                    );
                    if ($cues !== []) {
                        $cues = $this->validatedTranscriptCues($cues, $transcript, $index);
                    }

                    $media = $file ? $this->store($file, self::AUDIO_TYPES, $created) : $saved;
                    $configuration['tracks'][] = array_replace($media, [
                        'title' => $track['title'],
                        'artist' => $track['artist'] ?? null,
                        'transcript' => $transcript,
                        'transcript_cues' => $cues,
                    ]);
                }

                if (($configuration['homepage'] || $configuration['sidebar']) && $configuration['tracks'] === []) {
                    throw ValidationException::withMessages(['tracks' => 'Add an audio track before enabling public placement.'])->errorBag('promo');
                }

                $configuration['background'] = $request->boolean('remove_background') ? null : $current['background'];
                if (isset($data['background'])) {
                    $configuration['background'] = $this->store($data['background'], self::IMAGE_TYPES, $created);
                }

                DB::table('promo_player_settings')->where('id', 1)->update([
                    'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
                    'revision' => $current['revision'] + 1,
                ]);

                return array_diff(PromoPlayer::paths($current), PromoPlayer::paths($configuration));
            });
        } catch (Throwable $exception) {
            $this->removeFiles($created);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            throw ValidationException::withMessages(['tracks' => 'The campaign could not be saved. Please try again.'])->errorBag('promo');
        }

        $this->removeFiles($retired);

        return to_route('admin.system-settings.player.edit')->withFragment('promo-player')
            ->with('status', 'Promo Player settings saved.');
    }

    public function media(Request $request, string $media): Response
    {
        $settings = PromoPlayer::settings();
        $preview = $request->routeIs('admin.system-settings.player.promo.media');
        abort_unless($preview || PromoPlayer::visible($settings, 'homepage') || PromoPlayer::visible($settings, 'sidebar'), 404);

        $files = array_column(array_filter([$settings['background'], ...$settings['tracks']]), null, 'id');
        $file = $files[$media] ?? null;
        abort_unless($file, 404);

        return ManagedFiles::response($request, $file['path'], [
            'Content-Type' => $file['type'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], seekable: str_starts_with($file['type'], 'audio/'));
    }

    private function validatedTranscriptCues(array $cues, ?string $transcript, int|string $index): array
    {
        $invalid = fn () => ValidationException::withMessages([
            "tracks.$index.transcript_cues" => 'Transcript cues must follow the article text in timestamp order.',
        ])->errorBag('promo');

        if ($this->normalizeTranscript(implode(' ', array_column($cues, 'text'))) !== $this->normalizeTranscript($transcript)) {
            throw $invalid();
        }

        $lastStart = -1;
        foreach ($cues as &$cue) {
            $start = (float) $cue['start'];
            if ($start <= $lastStart) {
                throw $invalid();
            }
            $cue['start'] = $lastStart = $start;
        }

        return $cues;
    }

    private function normalizeTranscript(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text ?? ''));
    }

    private function store(UploadedFile $file, array $types, array &$created): array
    {
        $id = (string) Str::uuid();
        $type = $file->getMimeType();
        $name = $id.'.'.$types[$type];
        $created[] = PromoPlayer::DIRECTORY.'/'.$name;
        $path = ManagedFiles::store($file, PromoPlayer::DIRECTORY, $name);
        if (!$path) {
            throw new \RuntimeException('Promo Player upload failed.');
        }

        return ['id' => $id, 'type' => $type, 'path' => $path];
    }

    private function removeFiles(array $paths): void
    {
        if ($paths !== []) {
            try {
                ManagedFiles::disk()->delete($paths);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
