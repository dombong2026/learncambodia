<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CampaignCoverController extends Controller
{
    public function save(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $validated = $request->validate([
            'asset' => ['nullable', 'string', 'max:75', 'regex:/^[a-f0-9]{64}\.(jpg|png|webp)$/D'],
            'kind' => ['required', 'in:quiz,exam'],
        ]);
        $asset = $validated['asset'] ?? null;

        if ($asset !== null) {
            $file = storage_path('app/campaign-assets/'.$asset);
            if (!is_file($file)) {
                throw ValidationException::withMessages(['asset' => 'Upload a verified cover image first.']);
            }
            $dimensions = @getimagesize($file);
            if (($dimensions[0] ?? null) !== 1920 || ($dimensions[1] ?? null) !== 1080) {
                throw ValidationException::withMessages(['asset' => 'Cover images must be 1920 × 1080 pixels.']);
            }
        }

        $now = now();
        DB::table('homepage_campaign_cover')->updateOrInsert(
            ['id' => 1],
            [
                'asset' => $asset,
                'kind' => $validated['kind'],
                'updated_by' => $request->user()->id,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        return response()->json([
            'asset' => $asset,
            'kind' => $validated['kind'],
            'message' => 'Campaign featured cover saved.',
        ]);
    }
}
