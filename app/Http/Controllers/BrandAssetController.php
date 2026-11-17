<?php

namespace App\Http\Controllers;

use App\Support\PlatformBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BrandAssetController extends Controller
{
    public function image(string $slot, string $version): StreamedResponse
    {
        abort_unless(array_key_exists($slot, PlatformBranding::ASSETS), 404);
        $asset = PlatformBranding::settings()['assets'][$slot] ?? null;
        abort_unless($asset && $asset['version'] === $version, 404);

        $path = PlatformBranding::assetPath($slot, $version);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'branding-'.$slot, [
            'Content-Type' => $asset['mime'],
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function manifest(): JsonResponse
    {
        $manifest = json_decode(file_get_contents(public_path('brand/site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $manifest['name'] = PlatformBranding::name();
        $manifest['short_name'] = PlatformBranding::shortName();

        $manifest['icons'] = array_values(array_filter(array_map(function (array $icon): ?array {
            [$size] = explode('x', $icon['sizes']);
            $asset = PlatformBranding::asset('app-icon-'.$size);

            if ($asset['custom']) {
                // Custom app icons are not necessarily designed for maskable rendering.
                return $icon['purpose'] === 'any' ? [...$icon, 'src' => $asset['url']] : null;
            }

            return $icon;
        }, $manifest['icons'])));

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
