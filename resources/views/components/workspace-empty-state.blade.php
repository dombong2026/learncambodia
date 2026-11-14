@props([
    'title',
    'description' => null,
    'icon' => 'book-open',
    'href' => null,
    'action' => null,
])
<section {{ $attributes->class(['workspace-empty-state col-span-full']) }} data-workspace-empty-state role="status">
    <span class="workspace-empty-icon" aria-hidden="true"><x-workspace-icon :name="$icon" class="h-6 w-6" /></span>
    <h2 class="mt-4 text-base font-extrabold tracking-tight text-[#143761] dark:text-white">{{ $title }}</h2>
    @if($description)
        <p class="mt-2 max-w-lg text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $description }}</p>
    @endif
    @if($href && $action)
        <a href="{{ $href }}" class="mt-5 inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:bg-blue-600 dark:hover:bg-blue-500">{{ $action }}</a>
    @endif
</section>
