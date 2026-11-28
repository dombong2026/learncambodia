<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\CourseSupportChannels;
use App\Support\InstructorAccess;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CourseSupportSettingsController extends Controller
{
    public function edit(Request $request, Course $course): View
    {
        $assignment = $this->assignment($request, $course)->firstOrFail();

        return view('workspace.instructor.course-support-settings', [
            'role' => 'instructor',
            'course' => $course,
            'settings' => $assignment->support_channels ?? [],
            'examples' => CourseSupportChannels::EXAMPLES,
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $assignment = $this->assignment($request, $course);
        $platforms = array_keys(CourseSupportChannels::EXAMPLES);
        $data = $request->validate([
            'channels' => ['required', 'array:'.implode(',', $platforms)],
            'channels.*' => ['array:enabled,url'],
            'channels.*.enabled' => ['sometimes', 'boolean'],
            'channels.*.url' => ['nullable', 'string', 'max:255'],
        ]);

        $rules = [];
        foreach (CourseSupportChannels::EXAMPLES as $platform => $example) {
            $rules["channels.$platform.url"] = [
                Rule::requiredIf($request->boolean("channels.$platform.enabled")),
                'nullable',
                function (string $attribute, string $url, \Closure $fail) use ($platform, $example): void {
                    if (!CourseSupportChannels::accepts($platform, $url)) {
                        $fail(__('course_support.invalid_url', ['platform' => $platform, 'example' => $example]));
                    }
                },
            ];
        }
        $request->validate($rules);

        $settings = [];
        foreach ($platforms as $platform) {
            $settings[$platform] = [
                'enabled' => $request->boolean("channels.$platform.enabled"),
                'url' => $data['channels'][$platform]['url'] ?? null,
            ];
        }
        DB::transaction(function () use ($assignment, $settings): void {
            $record = $assignment->lockForUpdate()->firstOrFail();
            $record->support_channels = $settings;
            $record->save();
        });

        return to_route('instructor.courses.support.edit', [$course, 'lang' => app()->getLocale()])
            ->with('status', __('course_support.saved'));
    }

    private function assignment(Request $request, Course $course): HasMany
    {
        InstructorAccess::check($request->user(), $course);

        return $course->instructorAssignments()->where('instructor_id', $request->user()->id)->where('status', 'active');
    }
}
