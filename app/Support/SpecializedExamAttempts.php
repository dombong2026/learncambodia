<?php

namespace App\Support;

use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

final class SpecializedExamAttempts
{
    private const COOKIE = 'specialized_exam_browser';

    public static function browser(Request $request, bool $initialize = false): ?string
    {
        $token = $request->cookie(self::COOKIE);
        if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            if (!$initialize) {
                return null;
            }

            $token = bin2hex(random_bytes(32));
            Cookie::queue(cookie(self::COOKIE, $token, 60 * 24 * 90, '/', null,
                $request->isSecure() || app()->isProduction(), true, false, 'lax'));
        }

        return hash('sha256', $token);
    }

    public static function student(?User $user): ?User
    {
        return $user?->role === User::ROLE_STUDENT && $user->isActiveAccount() ? $user : null;
    }

    public static function participant(?User $user, string $kind): ?User
    {
        return $kind === 'quiz' && $user?->isActiveAccount() ? $user : self::student($user);
    }

    public static function visible(Builder $query, ?User $student, ?string $browser): Builder
    {
        return $query->whereNull('course_enrollment_id')->where(function (Builder $owner) use ($student, $browser): void {
            if ($student) {
                $owner->where('student_id', $student->id);
            }
            if ($browser !== null) {
                $owner->orWhere(fn (Builder $guest) => $guest->whereNull('student_id')->where('guest_browser', $browser));
            }
            if (!$student && $browser === null) {
                $owner->whereRaw('1 = 0');
            }
        });
    }

    public static function owns(Request $request, ExamAttempt $attempt): bool
    {
        $participant = self::participant($request->user(), $attempt->snapshot['kind'] ?? 'exam');

        return $attempt->isSpecialized()
            && ($attempt->student_id !== null
                ? $attempt->student_id === $participant?->id
                : self::ownsBrowser($request, $attempt));
    }

    public static function ownsBrowser(Request $request, ExamAttempt $attempt): bool
    {
        $browser = self::browser($request);

        return $attempt->isSpecialized() && $attempt->student_id === null && $browser !== null
            && $attempt->guest_browser !== null && hash_equals($attempt->guest_browser, $browser);
    }

    public static function emailKey(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }

    public static function claim(ExamAttempt $attempt, User $student): void
    {
        $attempt->forceFill(['student_id' => $student->id, 'guest_browser' => null, 'guest_email' => null])->save();
        CertificateAwards::assessment($attempt);
    }

    public static function afterLogin(Request $request, User $user): void
    {
        if (!self::student($user)) {
            return;
        }

        $next = ReadingReturn::valid($request->session()->get('auth.reading_return'));
        if ($next && preg_match('~\A/specialized-exams/attempts/([1-9][0-9]*)(?:\?|\z)~', $next, $match)) {
            DB::transaction(function () use ($match, $request, $user): void {
                $attempt = ExamAttempt::whereKey($match[1])->whereNotNull('submitted_at')->lockForUpdate()->first();
                if ($attempt && self::ownsBrowser($request, $attempt)
                    && ($attempt->guest_email === null || $attempt->guest_email === self::emailKey($user->email))) {
                    self::claim($attempt, $user);
                }
            });
        }

        $browser = self::browser($request);
        if ($browser !== null) {
            self::claimPendingEmail($user, $browser);
        }
        if ($user->wasRecentlyCreated) {
            self::claimVerifiedEmail($user);
        }
    }

    public static function claimVerifiedEmail(User $user): void
    {
        if (!self::student($user) || !$user->hasVerifiedEmail()) {
            return;
        }

        self::claimPendingEmail($user);
    }

    private static function claimPendingEmail(User $user, ?string $browser = null): void
    {
        $candidates = ExamAttempt::whereNull('student_id')->where('guest_email', self::emailKey($user->email))
            ->whereNotNull('submitted_at')->when($browser !== null, fn (Builder $query) => $query->where('guest_browser', $browser));
        (clone $candidates)->select('id')->chunkById(50, function ($pending) use ($user, $candidates): void {
            foreach ($pending as $candidate) {
                DB::transaction(function () use ($candidate, $user, $candidates): void {
                    $attempt = (clone $candidates)->whereKey($candidate->id)->lockForUpdate()->first();
                    if ($attempt?->isSpecialized()) {
                        self::claim($attempt, $user);
                    }
                });
            }
        });
    }
}
