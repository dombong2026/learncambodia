<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\OutboundMail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRouteName());
        }

        if (! OutboundMail::deliveryEnabled()) {
            return back()->with('status', __('auth.email_delivery_disabled'));
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Exception $error) {
            Log::warning('Email verification notification failed.', [
                'user_id' => $user->getKey(),
                'error_type' => get_class($error),
                'error_code' => (int) $error->getCode(),
            ]);

            return back()->with('verification_delivery_error', __('auth.verification_delivery_failed'));
        }

        return back()->with('status', __('auth.verification_link_sent'));
    }
}
