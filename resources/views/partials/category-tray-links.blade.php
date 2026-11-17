@foreach ($navigationCategories as $navigationCategory)
    <a data-category-short="{{ $navigationCategory->code }}" title="{{ $navigationCategory->name }}" aria-label="{{ $navigationCategory->code }} — {{ $navigationCategory->name }}" class="snap-start shrink-0 rounded-sm text-sm font-extrabold text-slate-600 transition hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-slate-300 dark:hover:text-blue-400" href="{{ route('categories.show', $navigationCategory) }}">{{ $navigationCategory->code }}</a>
@endforeach
