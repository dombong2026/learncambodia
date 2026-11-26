<?php

namespace App\Support;

use App\Models\AppearanceRevision;
use App\Models\EditorMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomeHeroMedia
{
    public const SECTION = 'home_hero_media';

    public static function settings(): array
    {
        return AppearanceRevision::approved()->where('section', self::SECTION)
            ->latest('id')->first()?->settings ?? ['media_id' => null, 'label' => null];
    }

    public static function accepts(EditorMedia $media): bool
    {
        return $media->context === 'library' && in_array($media->mime, FeaturedMedia::MIMES, true);
    }

    public const OPTIONS = [
        'autoplay' => true, 'muted' => true, 'advance' => true, 'loop' => true,
        'shuffle' => false, 'controls' => true, 'show_list' => false,
    ];

    public static function uses(EditorMedia $media): bool
    {
        if (!self::accepts($media)) {
            return false;
        }
        $settings = self::settings();
        if (isset($settings['playlist_ids'])) {
            return str_starts_with($media->mime, 'video/')
                && in_array((int) $media->id, $settings['playlist_ids'], true);
        }

        return (int) ($settings['media_id'] ?? 0) === (int) $media->id;
    }

    public static function current(): ?array
    {
        $settings = self::settings();
        if (isset($settings['playlist_ids'])) {
            $ids = $settings['playlist_ids'];
            $records = EditorMedia::whereIn('id', $ids)->where('context', 'library')
                ->whereIn('mime', ['video/mp4', 'video/webm'])->get()->keyBy('id');
            $items = collect($ids)->map(fn ($id) => $records->get($id))->filter()->values();
            if ($items->isEmpty()) {
                return null;
            }

            return [
                'kind' => 'playlist', 'media' => $items->first(), 'items' => $items,
                'label' => $settings['label'] ?? 'Learn Cambodia promotional playlist',
                'options' => array_replace(self::OPTIONS, $settings['options'] ?? []),
            ];
        }
        $id = (int) ($settings['media_id'] ?? 0);
        $media = $id > 0 ? EditorMedia::find($id) : null;
        if (!$media || !self::accepts($media)) {
            return null;
        }

        return [
            'media' => $media,
            'kind' => str_starts_with($media->mime, 'video/') ? 'video' : 'image',
            'label' => filled($settings['label'] ?? null) ? $settings['label'] : $media->original_name,
        ];
    }

    public static function selectPlaylist(User $actor, array $ids, array $options, ?string $label): void
    {
        $ids = array_map('intval', array_values($ids));
        if (count($ids) < 2 || count($ids) > 20 || count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['playlist_ids' => 'Choose 2 to 20 distinct videos.']);
        }
        DB::transaction(function () use ($actor, $ids, $options, $label): void {
            $records = EditorMedia::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($ids) || $records->contains(
                fn ($media) => !self::accepts($media) || !str_starts_with($media->mime, 'video/')
            )) {
                throw ValidationException::withMessages(['playlist_ids' => 'Choose only available Media Library videos.']);
            }
            $options = array_replace(self::OPTIONS, array_intersect_key($options, self::OPTIONS));
            if ($options['autoplay']) {
                $options['muted'] = true;
            }
            self::record($actor, ['playlist_ids' => $ids, 'options' => $options,
                'label' => $label ?: 'Learn Cambodia promotional playlist'], 'Published homepage video playlist.');
        }, 3);
    }

    private static function record(User $actor, array $settings, string $note): void
    {
        $previous = AppearanceRevision::approved()->where('section', self::SECTION)
            ->latest('id')->lockForUpdate()->first();
        $revision = AppearanceRevision::create([
            'section' => self::SECTION, 'settings' => $settings,
            'status' => 'approved', 'created_by' => $actor->id,
            'review_note' => 'Direct administrator homepage hero media selection.',
        ]);
        AppearanceRevision::approved()->where('section', self::SECTION)
            ->where('id', '<', $revision->id)->update(['status' => 'superseded']);
        ApprovalAudit::record('appearance', $revision->id, $actor, 'activate', $previous ? 'approved' : null, 'approved', $note);
    }

    public static function select(User $actor, ?int $id, ?string $label): void
    {
        DB::transaction(function () use ($actor, $id, $label): void {
            if ($id !== null) {
                $media = EditorMedia::query()->lockForUpdate()->find($id);
                if (!$media || !self::accepts($media)) {
                    throw ValidationException::withMessages(['media_id' => 'Select an image or video from the Media Library.']);
                }
            }
            self::record($actor, ['media_id' => $id, 'label' => $id !== null ? $label : null],
                $id === null ? 'Restored default homepage hero.' : 'Published homepage hero media #'.$id);
        }, 3);
    }
}
