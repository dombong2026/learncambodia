<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\PlayerSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminPlayerSettingsController extends Controller
{
    public function edit(): View
    {
        return view('workspace.admin.player-settings', ['mode' => PlayerSettings::audioPlayer()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audio_player' => ['required', Rule::in(array_keys(PlayerSettings::AUDIO_PLAYERS))],
        ]);

        PlayerSettings::updateAudioPlayer($data['audio_player']);

        return to_route('admin.system-settings.player.edit')->with('status', 'Audio player settings saved.');
    }
}
