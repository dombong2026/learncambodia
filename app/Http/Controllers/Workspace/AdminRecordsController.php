<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRecordsController extends Controller
{
    public function users(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(AdminAccess::ROLES))],
        ]);
        $query = trim($filters['q'] ?? '');
        $role = $filters['role'] ?? null;

        $users = User::query()->select(['id', 'public_id', 'name', 'email', 'role', 'created_at'])
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query) {
                $inner->where('name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->when(preg_match('/^[1-9][0-9]{7}$/D', $query), fn ($match) => $match->orWhere('public_id', $query));
            }))
            ->when($role, fn ($builder) => $builder->where('role', $role))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('workspace.admin.records', [
            'title' => 'User Manager',
            'description' => 'Create accounts and manage access to LearnCambodia.',
            'columns' => ['User ID', 'Name', 'Email', 'Role', 'Joined'],
            'userFilters' => ['q' => $query, 'role' => $role],
            'records' => $users->through(fn (User $user) => [
                ['value' => $user->public_id, 'url' => route('admin.users.show', $user)], $user->name, $user->email, AdminAccess::ROLES[$user->role] ?? $user->role, $user->created_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function user(User $user): View
    {
        return view('workspace.admin.user-record', ['account' => $user]);
    }

    public function courses(): View
    {
        $courses = Course::query()->with('category')
            ->orderByDesc('id')->paginate(10);

        return view('workspace.admin.records', [
            'title' => 'Course Manager',
            'description' => 'Review draft and published course records; publication remains subject to academic approval.',
            'columns' => ['Course ID', 'Course', 'Category', 'Level', 'Status'],
            'records' => $courses->through(fn (Course $course) => [
                ['value' => $course->public_id, 'url' => route('admin.course-builder.edit', $course)], $course->title, $course->category?->name, $course->cefr_level ?: 'Any', ucfirst($course->status),
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
