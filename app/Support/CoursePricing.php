<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CoursePricing
{
    public const TIMEZONE = 'Asia/Phnom_Penh';
    public const STRATEGIES = [
        'fixed' => 'Fixed price',
        'discount' => 'Discounted price',
        'increase' => 'Scheduled price increase',
        'seasonal' => 'Festive or seasonal offer',
    ];

    public static function validate(Request $request): array
    {
        if (!$request->has('pricing')) {
            return [];
        }

        $model = $request->validate(['pricing.model' => ['required', Rule::in(['free', 'paid', 'membership'])]])['pricing']['model'];
        if ($model === 'free') {
            return ['pricing' => null];
        }
        if ($model === 'membership') {
            $data = $request->validate(['pricing.plan' => ['required', 'string', 'max:100']])['pricing'];

            return ['pricing' => ['model' => $model, 'plan' => $data['plan']]];
        }

        $strategy = $request->validate(['pricing.strategy' => ['required', Rule::in(array_keys(self::STRATEGIES))]])['pricing']['strategy'];
        $currency = $request->validate(['pricing.currency' => ['required', Rule::in(['USD', 'KHR'])]])['pricing']['currency'];
        $scale = $currency === 'KHR' ? 1 : 100;
        $amount = [$scale === 1 ? 'integer' : 'decimal:0,2', 'numeric', 'gt:0', 'max:99999999'];
        $rules = ['pricing.base' => ['required', ...$amount]];
        if ($strategy !== 'fixed') {
            $rules['pricing.adjusted'] = ['required', ...$amount, $strategy === 'increase' ? 'gt:pricing.base' : 'lt:pricing.base'];
        }
        if ($strategy === 'increase' || $strategy === 'seasonal') {
            $rules['pricing.starts'] = ['required', 'date_format:Y-m-d\TH:i'];
        }
        if ($strategy === 'seasonal') {
            $rules['pricing.ends'] = ['required', 'date_format:Y-m-d\TH:i', 'after:pricing.starts'];
            $rules['pricing.label'] = ['required', 'string', 'max:80'];
        }
        $data = $request->validate($rules)['pricing'];
        $pricing = [
            'model' => $model, 'strategy' => $strategy, 'currency' => $currency,
            'base_minor' => (int) round($data['base'] * $scale),
        ];
        if (isset($data['adjusted'])) {
            $pricing['adjusted_minor'] = (int) round($data['adjusted'] * $scale);
        }
        foreach (['starts', 'ends', 'label'] as $key) {
            if (isset($data[$key])) {
                $pricing[$key] = $data[$key];
            }
        }

        return ['pricing' => $pricing];
    }

    public static function quote(?array $pricing, ?CarbonImmutable $at = null): array
    {
        $model = $pricing['model'] ?? 'free';
        if ($model !== 'paid') {
            return $pricing ?? ['model' => 'free'];
        }

        $time = ($at ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i');
        $active = match ($pricing['strategy']) {
            'discount' => true,
            'increase' => $time >= $pricing['starts'],
            'seasonal' => $time >= $pricing['starts'] && $time < $pricing['ends'],
            default => false,
        };

        return [
            'model' => 'paid', 'currency' => $pricing['currency'],
            'amount_minor' => $active ? $pricing['adjusted_minor'] : $pricing['base_minor'],
            'base_minor' => $pricing['base_minor'], 'offer' => $active ? ($pricing['label'] ?? null) : null,
        ];
    }

    public static function formatAmount(int $amount, string $currency): string
    {
        return $currency === 'KHR'
            ? 'KHR '.number_format($amount)
            : 'USD '.number_format($amount / 100, 2);
    }
}
