<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\PlatformBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class AdminBrandingController extends Controller
{
    public function edit(): View
    {
        return view('workspace.admin.branding', [
            'locales' => config('learning.supported_locales'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'short_name' => ['required', 'string', 'max:32'],
            'translations' => ['sometimes', 'array:'.implode(',', array_keys(config('learning.supported_locales')))],
            'uploads' => ['sometimes', 'array:'.implode(',', array_keys(PlatformBranding::ASSETS))],
        ];

        foreach (array_keys(config('learning.supported_locales')) as $locale) {
            $rules['translations.'.$locale] = ['array:tagline,meta_title,meta_description'];
            $rules['translations.'.$locale.'.tagline'] = ['nullable', 'string', 'max:120'];
            $rules['translations.'.$locale.'.meta_title'] = ['nullable', 'string', 'max:80'];
            $rules['translations.'.$locale.'.meta_description'] = ['nullable', 'string', 'max:200'];
        }

        foreach (PlatformBranding::ASSETS as $slot => $asset) {
            $dimensions = match (true) {
                str_starts_with($slot, 'logo-') => 'dimensions:min_width=200,min_height=40,max_width=3000,max_height=1200',
                $slot === 'share-image' => 'dimensions:min_width=600,min_height=315,max_width=2400,max_height=1260',
                default => 'dimensions:width='.$asset['width'].',height='.$asset['height'],
            };

            $formats = $slot === 'share-image' ? ['png', 'jpg', 'jpeg', 'webp'] : ['png'];
            $rules['uploads.'.$slot] = ['nullable', File::image()->max(2048), File::types($formats), $dimensions];
        }

        $values = $request->validate($rules);
        $disk = Storage::disk('local');
        $assets = [];
        $staged = [];

        try {
            foreach ($values['uploads'] ?? [] as $slot => $file) {
                if ($file === null) {
                    continue;
                }

                $image = getimagesize($file->getPathname());
                $version = (string) Str::ulid();
                $path = PlatformBranding::assetPath($slot, $version);
                $staged[] = $path;

                if (! $disk->putFileAs(dirname($path), $file, $version)) {
                    throw new RuntimeException('Unable to store the branding image.');
                }

                $assets[$slot] = [
                    'version' => $version,
                    'width' => $image[0],
                    'height' => $image[1],
                    'mime' => $image['mime'],
                ];
            }

            $superseded = DB::transaction(function () use ($values, $assets): array {
                $row = DB::table('platform_branding')->where('id', 1);
                $stored = $row->lockForUpdate()->value('settings');

                if ($stored === null) {
                    throw new RuntimeException('Platform branding settings have not been initialized.');
                }

                $settings = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
                $settings['name'] = $values['name'];
                $settings['short_name'] = $values['short_name'];

                foreach ($values['translations'] ?? [] as $locale => $strings) {
                    foreach ($strings as $key => $value) {
                        $settings['translations'][$locale][$key] = $value;
                    }
                }

                $previous = $settings['assets'] ?? [];
                $settings['assets'] = array_replace($previous, $assets);
                $row->update(['settings' => json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);

                return array_intersect_key($previous, $assets);
            });
        } catch (Throwable $exception) {
            $disk->delete($staged);
            throw $exception;
        }

        foreach ($superseded as $slot => $previous) {
            $disk->delete(PlatformBranding::assetPath($slot, $previous['version']));
        }

        return to_route('admin.system-settings.branding', $request->only('lang'))
            ->with('status', 'Branding saved.');
    }
}
