@php
    $saved = $course->pricing ?? [];
    $scale = ($saved['currency'] ?? 'USD') === 'KHR' ? 1 : 100;
    $model = old('pricing.model', $saved['model'] ?? 'free');
    $strategy = old('pricing.strategy', $saved['strategy'] ?? 'fixed');
@endphp
<fieldset class="builder-editor min-w-0 space-y-4 sm:col-span-2" data-course-pricing>
    <legend class="px-2 text-sm font-bold">Pricing</legend>
    <label class="builder-field">Pricing model
        <select name="pricing[model]" data-pricing-model class="w-full rounded-xl border px-3 py-2">
            <option value="free" @selected($model === 'free')>Free</option>
            <option value="paid" @selected($model === 'paid')>Paid</option>
            <option value="membership" @selected($model === 'membership')>Membership</option>
        </select>
    </label>
    <p class="text-xs text-slate-600 dark:text-slate-300">Paid and membership enrollments use administrator verification. Online checkout is unavailable.</p>
    <div data-pricing-mode="paid" @if($model !== 'paid') hidden @endif class="grid min-w-0 gap-4 sm:grid-cols-2">
        <label class="builder-field">Currency
            <select name="pricing[currency]" class="rounded-xl border px-3 py-2">
                @foreach(['USD', 'KHR'] as $currency)
                    <option @selected(old('pricing.currency', $saved['currency'] ?? 'USD') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </label>
        <label class="builder-field">Base price
            <input name="pricing[base]" type="number" min="0.01" step="0.01" value="{{ old('pricing.base', isset($saved['base_minor']) ? $saved['base_minor'] / $scale : '') }}">
        </label>
        <label class="builder-field sm:col-span-2">Pricing option
            <select name="pricing[strategy]" data-pricing-strategy class="rounded-xl border px-3 py-2">
                @foreach(\App\Support\CoursePricing::STRATEGIES as $key => $label)
                    <option value="{{ $key }}" @selected($strategy === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label data-pricing-option="discount increase seasonal" @if($strategy === 'fixed') hidden @endif class="builder-field">Adjusted price
            <input name="pricing[adjusted]" type="number" min="0.01" step="0.01" value="{{ old('pricing.adjusted', isset($saved['adjusted_minor']) ? $saved['adjusted_minor'] / $scale : '') }}">
        </label>
        <label data-pricing-option="increase seasonal" @if(!in_array($strategy, ['increase', 'seasonal'], true)) hidden @endif class="builder-field">Starts (Cambodia time)
            <input name="pricing[starts]" type="datetime-local" value="{{ old('pricing.starts', $saved['starts'] ?? '') }}">
        </label>
        <label data-pricing-option="seasonal" @if($strategy !== 'seasonal') hidden @endif class="builder-field">Ends (Cambodia time)
            <input name="pricing[ends]" type="datetime-local" value="{{ old('pricing.ends', $saved['ends'] ?? '') }}">
        </label>
        <label data-pricing-option="seasonal" @if($strategy !== 'seasonal') hidden @endif class="builder-field">Offer name
            <input name="pricing[label]" maxlength="80" value="{{ old('pricing.label', $saved['label'] ?? '') }}">
        </label>
    </div>
    <label data-pricing-mode="membership" @if($model !== 'membership') hidden @endif class="builder-field">Required membership
        <input name="pricing[plan]" maxlength="100" value="{{ old('pricing.plan', $saved['plan'] ?? '') }}">
    </label>
</fieldset>
