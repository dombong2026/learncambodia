<form id="course-details-form" data-builder-edit method="POST" action="{{ $course->exists ? route('admin.course-builder.update', $course) : route('admin.course-builder.store') }}">
    @csrf
    @if($course->exists)@method('PATCH')@endif
    <fieldset @disabled(!$editable) class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2"><label for="course-title" class="mb-2 block text-sm font-bold">Course title</label><input id="course-title" name="title" maxlength="180" required value="{{ old('title',$course->title) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-slug" class="mb-2 block text-sm font-bold">URL slug (optional)</label><input id="course-slug" name="slug" maxlength="180" value="{{ old('slug',$course->slug) }}" placeholder="Auto-generated if blank" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-category" class="mb-2 block text-sm font-bold">Category</label><select id="course-category" name="category_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Select a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)old('category_id',$course->category_id)===$category->id)>{{ $category->code }} · {{ $category->name }} {{ $category->is_published ? '' : '(unpublished)' }}</option>@endforeach</select>@error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-level" class="mb-2 block text-sm font-bold">CEFR level</label><select id="course-level" name="cefr_level" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All levels</option>@foreach($levels as $level)<option value="{{ $level->code }}" @selected(old('cefr_level',$course->cefr_level)===$level->code)>{{ $level->code }} · {{ $level->name }}</option>@endforeach</select>@error('cefr_level')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-mode" class="mb-2 block text-sm font-bold">Delivery mode</label><select id="course-mode" name="delivery_mode" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['online'=>'Online','onsite'=>'On-site','hybrid'=>'Hybrid'] as $key=>$name)<option value="{{ $key }}" @selected(old('delivery_mode',$course->delivery_mode ?? 'online')===$key)>{{ $name }}</option>@endforeach</select></div>
        <div class="sm:col-span-2"><label for="course-summary" class="mb-2 block text-sm font-bold">Summary</label><textarea id="course-summary" name="summary" maxlength="1000" rows="3" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('summary',$course->summary) }}</textarea>@error('summary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="min-w-0 sm:col-span-2">
            <label id="course-description-label" for="course-description-plain" class="mb-2 block text-sm font-bold">Full course description</label>
            @if($editable)
                <x-rich-editor id="course-description" context="course" name="description" html-name="description_html"
                    labelled-by="course-description-label" :required="false"
                    :value="old('description', $course->description)" :html="old('description_html', $course->description_html)" />
            @else
                <x-course-description :course="$course" class="text-sm leading-7" />
            @endif
        </div>
    </fieldset>
</form>
