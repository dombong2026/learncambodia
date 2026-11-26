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

    public static function uses(EditorMedia $media): bool
    {
        return self::accepts($media) && (int) (self::settings()['media_id'] ?? 0) === (int) $media->id;
    }

    public static function current(): ?array
    {
        $settings = self::settings();
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

    public static function select(User $actor, ?int $id, ?string $label): void
    {
        DB::transaction(function () use ($actor, $id, $label): void {
            if ($id !== null) {
                $media = EditorMedia::query()->lockForUpdate()->find($id);
                if (!$media || !self::accepts($media)) {
                    throw ValidationException::withMessages(['media_id' => 'Select an image or video from the Media Library.']);
                }
            }
            $previous = AppearanceRevision::approved()->where('section', self::SECTION)
                ->latest('id')->lockForUpdate()->first();
            $revision = AppearanceRevision::create([
                'section' => self::SECTION,
                'settings' => ['media_id' => $id, 'label' => $id !== null ? $label : null],
                'status' => 'approved', 'created_by' => $actor->id,
                'review_note' => 'Direct administrator homepage hero media selection.',
            ]);
            AppearanceRevision::approved()->where('section', self::SECTION)
                ->where('id', '<', $revision->id)->update(['status' => 'superseded']);
            ApprovalAudit::record('appearance', $revision->id, $actor, 'activate', $previous ? 'approved' : null,
                'approved', $id === null ? 'Restored default homepage hero.' : 'Published homepage hero media #'.$id);
        }, 3);
    }
}
