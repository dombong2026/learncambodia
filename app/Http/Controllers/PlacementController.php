<?php

namespace App\Http\Controllers;

use App\Models\CourseExam;
use Illuminate\View\View;

final class PlacementController extends Controller
{
    public function index(): View
    {
        $tracks = $this->tracks();
        $exams = $this->exams(array_keys($tracks));

        return view('placement.index', compact('tracks', 'exams'));
    }

    public function show(string $track): View
    {
        $entry = $this->tracks()[$track] ?? abort(404);
        $exam = $this->exams([$track])->get('placement-'.$track);

        return view('placement.show', compact('entry', 'exam'));
    }

    private function tracks(): array
    {
        return json_decode(file_get_contents(resource_path('placement/curriculum.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function exams(array $tracks): \Illuminate\Support\Collection
    {
        $keys = array_map(static fn (string $track): string => 'placement-'.$track, $tracks);

        return CourseExam::query()->whereIn('starter_key', $keys)
            ->where('published', true)->whereNull('deactivated_at')
            ->get(['id', 'public_id', 'title', 'kind', 'starter_key', 'time_limit'])
            ->keyBy('starter_key');
    }
}
