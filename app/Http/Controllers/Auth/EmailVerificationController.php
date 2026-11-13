<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EmailVerificationController extends Controller
{
    public function notice(): View
    {
        return view('auth.confirm-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route($request->user()->homeRouteName())
            ->with('status', __('auth.email_confirmed'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->homeRouteName());
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('status', __('auth.email_delivery_disabled'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('auth.verification_link_sent'));
    }
}
