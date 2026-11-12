<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperTokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspace.developer.tokens', [
            'role' => 'developer',
            'tokens' => $request->user()->tokens()->orderByDesc('id')->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'current_password' => ['required', 'current_password'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $user = $request->user();

        if ($user->tokens()->where('expires_at', '>', now())->count() >= 10) {
            return back()->withErrors(['name' => 'Limit reached: revoke an active token before creating another.']);
        }

        $token = $user->createToken(
            $validated['name'],
            ['catalog:read'],
            now()->addDays((int) $validated['days'])
        );

        // This plaintext value is shown on the following response only.
        // Sanctum persists only the SHA-256 hash, never the plaintext token.
        return redirect()->route('developer.tokens.index')
            ->with('issued_token', $token->plainTextToken)
            ->with('status', 'Token created. Copy it now; it cannot be shown again.');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $record = $request->user()->tokens()->whereKey($token)->firstOrFail();
        $record->delete();

        return back()->with('status', 'Token revoked.');
    }
}
