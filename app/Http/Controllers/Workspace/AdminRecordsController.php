<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRecordsController extends Controller
{
    public function users(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        abort_if(mb_strlen($query) > 80, 422);

        $users = User::query()->select(['id', 'name', 'email', 'role', 'created_at'])
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query) {
                $inner->where('name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%');
            }))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('workspace.admin.records', [
            'title' => 'User Manager',
            'description' => 'Read-only account directory. Role changes require an independently approved operator workflow.',
            'columns' => ['Name', 'Email', 'Role', 'Joined'],
            'records' => $users->through(fn (User $user) => [
                $user->name, $user->email, ucfirst($user->role), $user->created_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function courses(): View
    {
        $courses = Course::query()->with('category')
            ->orderByDesc('id')->paginate(10);

        return view('workspace.admin.records', [
            'title' => 'Course Manager',
            'description' => 'Review draft and published course records; publication remains subject to academic approval.',
            'columns' => ['Course', 'Category', 'Level', 'Status'],
            'records' => $courses->through(fn (Course $course) => [
                $course->title, $course->category?->name, $course->cefr_level ?: 'Any', ucfirst($course->status),
            ]),
        ]);
    }

    public function cefr(): View
    {
        $levels = CefrLevel::query()->orderBy('sort_order')->paginate(10);

        return view('workspace.admin.records', [
            'title' => 'CEFR Level Manager',
            'description' => 'Six reference levels. These are descriptors, not an official examination or test result.',
            'columns' => ['Code', 'Category', 'Level Name', 'Description'],
            'records' => $levels->through(fn (CefrLevel $level) => [
                $level->code, $level->category, $level->name, $level->description,
            ]),
        ]);
    }
}
