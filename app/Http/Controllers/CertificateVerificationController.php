<?php

namespace App\Http\Controllers;

use App\Models\IssuedCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class CertificateVerificationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $present = $request->query->has('code');
        $input = $request->query()['code'] ?? null;
        $input = is_string($input) && strlen($input) <= 350 ? trim($input) : '';
        $code = $this->certificateCode($input);
        $status = $present ? 'invalid' : null;
        $record = null;

        if ($code !== null) {
            $certificate = IssuedCertificate::query()->where('code', $code)
                ->first(['code', 'snapshot', 'created_at', 'revoked_at']);
            $status = match (true) {
                $certificate === null => 'unknown',
                $certificate->revoked_at !== null => 'revoked',
                default => 'verified',
            };

            if ($status === 'verified') {
                $record = [
                    'learner' => $certificate->snapshot['learner'],
                    'course' => $certificate->snapshot['course'] ?? null,
                    'assessment' => $certificate->snapshot['assessment'] ?? null,
                    'credential_type' => isset($certificate->snapshot['kind'])
                        ? __('certificate_verification.kind_'.$certificate->snapshot['kind']) : null,
                    'issuer' => $certificate->snapshot['issuer'],
                    'date' => $certificate->created_at->toDateString(),
                    'code' => $certificate->code,
                ];
                $record = array_filter($record, fn ($value) => $value !== null && $value !== '');
            }
        }

        return response()->view('learning.verify-certificate', compact('input', 'code', 'status', 'record'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function certificateCode(string $input): ?string
    {
        if (Str::isUuid($input)) {
            return strtolower($input);
        }

        $url = parse_url($input);
        $verification = parse_url(route('certificates.verify'));
        if (
            !isset($url['scheme'], $url['host'], $url['path'], $url['query']) ||
            $url['scheme'] !== $verification['scheme'] ||
            strcasecmp($url['host'], $verification['host']) !== 0 ||
            ($url['port'] ?? null) !== ($verification['port'] ?? null) ||
            $url['path'] !== $verification['path'] ||
            isset($url['user']) || isset($url['pass'])
        ) {
            return null;
        }

        parse_str($url['query'], $query);
        $code = $query['code'] ?? null;

        return is_string($code) && Str::isUuid($code) ? strtolower($code) : null;
    }
}
