<div class="builder-media-grid">
    <div class="builder-cover">
        @if($course->featured_kind)
            <x-editorial-featured-media :entry="$course" />
        @else
            <div class="flex aspect-video flex-col items-center justify-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300">
                <x-workspace-icon name="media-library" class="h-9 w-9" /> No featured media saved
            </div>
        @endif
    </div>
    @if($editable)
        <form id="course-media-form" data-builder-edit method="POST" action="{{ route('admin.course-builder.media', $course) }}">
            @csrf @method('PATCH')
            @include('workspace.admin.editorial-featured-media', ['entry' => $course])
        </form>
        @include('workspace.admin.media-picker')
    @else
        <dl class="space-y-3 text-sm">
            <div><dt class="font-semibold">Media type</dt><dd>{{ ucfirst($course->featured_kind ?? 'None') }}</dd></div>
            @if($course->featuredMedia)<div><dt class="font-semibold">File</dt><dd class="break-words">{{ $course->featuredMedia->original_name }}</dd></div>@endif
            @if($course->featured_alt)<div><dt class="font-semibold">Image description</dt><dd>{{ $course->featured_alt }}</dd></div>@endif
        </dl>
    @endif
</div>
