<?php

namespace App\Support;

use App\Models\AppearanceRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class WebsiteSkinState
{
    private const SECTION = 'website_skins';
    private const REQUEST_KEY = 'website_skin.resolved';

    public static function configuration(): array
    {
        return AppearanceRevision::query()->approved()->where('section', self::SECTION)
            ->latest('id')->first()?->settings ?? ['mode' => 'legacy', 'periods' => []];
    }

    public static function active(): ?string
    {
        $attributes = request()->attributes;
        if ($attributes->has(self::REQUEST_KEY)) {
            return $attributes->get(self::REQUEST_KEY);
        }
        $skin = WebsiteSkins::resolve(self::configuration(), now());
        $attributes->set(self::REQUEST_KEY, $skin);

        return $skin;
    }

    public static function preview(string $skin): void
    {
        abort_unless(WebsiteSkins::valid($skin), 404);
        request()->attributes->set(self::REQUEST_KEY, $skin);
    }

    public static function change(User $actor, callable $change): void
    {
        DB::transaction(function () use ($actor, $change): void {
            $previous = AppearanceRevision::query()->approved()->where('section', self::SECTION)
                ->latest('id')->lockForUpdate()->first();
            $settings = $change($previous?->settings ?? ['mode' => 'legacy', 'periods' => []]);
            $revision = AppearanceRevision::create([
                'section' => self::SECTION,
                'settings' => $settings,
                'status' => 'approved',
                'created_by' => $actor->id,
                'review_note' => 'Direct administrator website skin configuration.',
            ]);
            AppearanceRevision::query()->approved()->where('section', self::SECTION)
                ->where('id', '<', $revision->id)->update(['status' => 'superseded']);
            ApprovalAudit::record('appearance', $revision->id, $actor, 'activate', null, 'approved',
                'Website skin mode: '.($settings['mode'] ?? 'legacy').'; selection: '.($settings['manual_skin'] ?? 'none'));
        });
        request()->attributes->remove(self::REQUEST_KEY);
    }
}
