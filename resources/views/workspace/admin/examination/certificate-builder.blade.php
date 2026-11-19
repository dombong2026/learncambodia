@extends('layouts.workspace')
@section('heading', 'Certificate Builder')
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/certificate-designer.css') }}?v=2">
    <script defer src="{{ asset('js/cert-qr-qrcode-generator.js') }}"></script>
    <script defer src="{{ asset('js/certificate-qr.js') }}"></script>
    <script defer src="{{ asset('js/certificate-designer.js') }}?v=2"></script>
@endpush

@section('content')
    @php
        $design = \App\Support\CertificateDesign::options(old('design', $template->design));
        $background = !empty($template->design['background']) ? route('admin.certificate-templates.background', $template) : null;
        $text = function (string $key, string $default): string {
            $value = old($key, $default);

            return is_string($value) ? $value : $default;
        };
        $sample = [
            'type' => $types->firstWhere('id', (int) old('certificate_type_id', $template->certificate_type_id))?->name ?? 'Course completion',
            'heading' => $text('heading', $template->heading ?? 'Certificate of Completion'),
            'learner' => 'Sample Learner',
            'course' => 'Sample Course',
            'assessment' => $template->type?->kind === 'quiz' ? 'Sample Quiz' : 'Sample Exam',
            'body' => $text('body', $template->body ?? 'For successfully completing {course}.'),
            'issuer' => $text('issuer', $template->issuer ?? 'LearnCambodia'),
            'date' => 'Issued '.now()->toDateString(),
            'code' => '00000000-0000-0000-0000-000000000000',
            'accent' => $text('accent', $template->accent ?? '#8b5e18'),
            'orientation' => $text('orientation', $template->orientation ?? 'landscape'),
            'design' => $design,
        ];
    @endphp
    @if($errors->any())
        <ul role="alert" class="mb-4 list-disc pl-5 text-red-600 dark:text-red-300">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <div class="cert-editor" data-cert-builder>
        <form id="certificate-design-form" method="post" enctype="multipart/form-data"
            action="{{ $template->exists ? route('admin.certificate-templates.update', $template) : route('admin.certificate-templates.store') }}"
            class="workspace-panel cert-settings">
            @csrf
            @if($template->exists) @method('PATCH') @endif
            <fieldset>
                <legend>Template details</legend>
                <label class="builder-field">Template name<input name="name" required maxlength="100" value="{{ $text('name', $template->name ?? '') }}"></label>
                <div class="builder-field">
                    <label for="certificate-type">Certificate type</label>
                    <select id="certificate-type" name="certificate_type_id" required>
                        <option value="">Choose a certificate type</option>
                        @foreach($types as $type)<option value="{{ $type->id }}" data-certificate-kind="{{ $type->kind }}" @selected((int) old('certificate_type_id', $template->certificate_type_id) === $type->id)>{{ $type->name }}</option>@endforeach
                    </select>
                </div>
                <a class="cert-hint text-blue-700 dark:text-blue-300" href="{{ route('admin.certificate-types.index') }}">Manage certificate types</a>
            </fieldset>
            <fieldset>
                <legend>Certificate artwork</legend>
                <div class="cert-drop" data-cert-drop>
                    <x-workspace-icon name="cloud-storage" class="cert-drop-icon" />
                    <div>
                        <label for="certificate-background">Drag &amp; drop or upload</label>
                        <p id="certificate-background-help">PNG, JPG or WebP · Up to 8 MB</p>
                    </div>
                    <input id="certificate-background" name="background" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="certificate-background-help">
                </div>
                <p data-cert-file-status aria-live="polite" class="cert-hint"></p>
                @if($background)
                    <label class="cert-check"><input type="checkbox" name="remove_background" value="1" @checked(old('remove_background'))> Remove saved artwork</label>
                @endif
                <p class="cert-hint">The artwork fills the A4 page. Use an A4 image in the same orientation for a full-page fit.</p>
            </fieldset>
            <fieldset>
                <legend>Page and text</legend>
                <div class="cert-settings-grid">
                    <label class="builder-field">A4 orientation
                        <select name="orientation">
                            @foreach(['landscape', 'portrait'] as $orientation)<option @selected($sample['orientation'] === $orientation) value="{{ $orientation }}">{{ ucfirst($orientation) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="builder-field">Typeface
                        <select name="design[font]">
                            <option value="serif" @selected($design['font'] === 'serif')>Serif</option>
                            <option value="sans" @selected($design['font'] === 'sans')>Sans serif</option>
                        </select>
                    </label>
                    <label class="builder-field">Frame accent<input name="accent" type="color" value="{{ $sample['accent'] }}"></label>
                    <label class="builder-field">Text color<input name="design[ink]" type="color" value="{{ $design['ink'] }}"></label>
                </div>
                <label class="cert-check">
                    <input type="hidden" name="design[frame]" value="0">
                    <input type="checkbox" name="design[frame]" value="1" @checked($design['frame'])> Show decorative frame
                </label>
                <label class="builder-field">Heading<input name="heading" required maxlength="140" value="{{ $sample['heading'] }}"></label>
                <label class="builder-field">Certificate text<textarea name="body" required maxlength="700" rows="3">{{ $sample['body'] }}</textarea></label>
                <p class="cert-hint">Use <code>{learner}</code> for the recipient, <code>{course}</code> for a course name, and <code>{assessment}</code> for an exam or quiz title.</p>
                <label class="builder-field">Issuer<input name="issuer" required maxlength="140" value="{{ $sample['issuer'] }}"></label>
            </fieldset>
            <label class="builder-field">Assigned courses
                <select name="courses[]" multiple size="5" aria-describedby="cert-course-help">
                    @foreach($courses as $course)<option value="{{ $course->id }}" @selected(in_array($course->id, array_map('intval', (array) old('courses', $assigned)), true))>{{ $course->title }}</option>@endforeach
                </select>
                <span class="cert-hint" id="cert-course-help">Assign Course templates here. Choose Exam and Quiz templates in their assessment builders.</span>
            </label>
            <button type="submit" class="cert-button cert-primary">Save template</button>
        </form>
        <section aria-labelledby="certificate-preview-title" class="workspace-panel cert-preview">
            <div class="cert-preview-bar">
                <div>
                    <h2 id="certificate-preview-title">A4 certificate preview</h2>
                    <p class="cert-hint">Drag the text or QR code, or enter its position below.</p>
                </div>
                <button type="button" class="cert-button" data-cert-print>Print / Save as PDF</button>
            </div>
            <div class="cert-preview-stage">
                <x-certificate-paper :certificate="$sample" :background="$background" editable />
            </div>
            <details class="cert-position-panel" open>
                <summary>Field position and size</summary>
                <div class="cert-position-grid" role="group" aria-label="Field position and size">
                    @foreach(\App\Support\CertificateDesign::FIELDS as $key => $field)
                        <fieldset data-cert-controls="{{ $key }}">
                            <legend>{{ $field['label'] }}</legend>
                            @foreach(['x' => 'Left (%)', 'y' => 'Top (%)', 'size' => 'Size (%)'] as $axis => $label)
                                @php([$min, $max] = \App\Support\CertificateDesign::limits($key)[$axis])
                                <label>
                                    {{ $label }}
                                    <input form="certificate-design-form" type="number" name="design[fields][{{ $key }}][{{ $axis }}]"
                                        data-cert-axis="{{ $axis }}" required min="{{ $min }}"
                                        max="{{ $max }}" step="0.1" value="{{ $design['fields'][$key][$axis] }}">
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            </details>
        </section>
    </div>
@endsection
