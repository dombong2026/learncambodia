<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Controller;
use App\Models\AppearanceRevision;
use App\Support\WebsiteSkins;
use App\Support\WebsiteSkinState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminWebsiteSkinController extends Controller
{
    public function index(): View
    {
        return view('workspace.admin.website-skins', [
            'role' => 'admin',
            'skins' => WebsiteSkins::CATALOG,
            'settings' => WebsiteSkinState::configuration(),
            'activeSkin' => WebsiteSkinState::active(),
            'history' => AppearanceRevision::where('section', 'website_skins')->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['manual', 'automatic', 'legacy'])],
            'skin' => ['required_if:mode,manual', 'nullable', Rule::in(array_keys(WebsiteSkins::CATALOG))],
        ]);
        WebsiteSkinState::change($request->user(), static function (array $settings) use ($data): array {
            $settings['mode'] = $data['mode'];
            if ($data['mode'] === 'manual') {
                $settings['manual_skin'] = $data['skin'];
            }

            return $settings;
        });

        return back()->with('status', 'Website skin mode updated immediately.');
    }

    public function addPeriod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'skin' => ['required', Rule::in(array_keys(WebsiteSkins::CATALOG))],
        ]);
        WebsiteSkinState::change($request->user(), static function (array $settings) use ($data): array {
            $periods = $settings['periods'] ?? [];
            if (count($periods) >= 20) {
                throw ValidationException::withMessages(['start' => 'Remove an existing period before adding another.']);
            }
            foreach ($periods as $period) {
                if ($data['start'] <= $period['end'] && $period['start'] <= $data['end']) {
                    throw ValidationException::withMessages(['start' => 'This period overlaps an existing schedule.']);
                }
            }
            $periods[] = $data;
            $settings['periods'] = $periods;

            return $settings;
        });

        return back()->with('status', 'Scheduled skin period saved.');
    }

    public function removePeriod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
            'skin' => ['required', Rule::in(array_keys(WebsiteSkins::CATALOG))],
        ]);
        WebsiteSkinState::change($request->user(), static function (array $settings) use ($data): array {
            $settings['periods'] = array_values(array_filter($settings['periods'] ?? [],
                static fn (array $period): bool => !($period['start'] === $data['start']
                    && $period['end'] === $data['end'] && $period['skin'] === $data['skin'])));

            return $settings;
        });

        return back()->with('status', 'Scheduled skin period removed.');
    }

    public function preview(string $skin, CatalogController $catalog): Response
    {
        WebsiteSkinState::preview($skin);

        return response($catalog->home()->render())->header('Cache-Control', 'private, no-store');
    }
}
