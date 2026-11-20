@props(['resources', 'enrollment'])
<ul class="divide-y divide-slate-200 dark:divide-slate-700">
    @foreach($resources as $resource)
        <li class="py-3">
            <a class="flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-semibold hover:bg-blue-50 dark:hover:bg-slate-800"
                href="{{ route('account.enrollments.resources.download', [$enrollment, $resource]) }}">
                <x-workspace-icon name="file" class="h-5 w-5 shrink-0" />
                <span class="min-w-0 flex-1 break-words">{{ $resource->title }}
                    <small class="block break-all text-slate-600 dark:text-slate-300">{{ $resource->media->original_name }} · {{ number_format($resource->media->size / 1024) }} KB</small>
                </span>
                <span>{{ __('focused_learning.download_resource') }}</span>
            </a>
        </li>
    @endforeach
</ul>
