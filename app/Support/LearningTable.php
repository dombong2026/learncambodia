<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class LearningTable
{
    public static function search(Request $request): string
    {
        $value = $request->query('q', '');

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }

    public static function courses(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $courses) => $courses->where(
            fn (Builder $row) => $row->whereLike('title', "%$search%")
                ->orWhereLike('cefr_level', "%$search%")
                ->orWhereHas('category', fn (Builder $category) => $category->whereLike('name', "%$search%")->orWhereLike('code', "%$search%"))
                ->when(ctype_digit($search) && strlen($search) === 6, fn (Builder $match) => $match->orWhere('public_id', $search))
        ));
    }

    public static function assessments(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $exams) => $exams->where(
            fn (Builder $row) => $row->whereLike('title', "%$search%")
                ->orWhereHas('type', fn (Builder $type) => $type->whereLike('name', "%$search%"))
                ->orWhereHas('course', fn (Builder $course) => self::courses($course, $search))
                ->when(ctype_digit($search) && strlen($search) === 6, fn (Builder $match) => $match->orWhere('public_id', $search))
        ));
    }

    public static function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        $total = $query->toBase()->getCountForPagination();
        $last = max(1, (int) ceil($total / 10));
        $page = min($last, (int) LengthAwarePaginator::resolveCurrentPage());
        $filters = array_filter(['q' => self::search($request), 'lang' => $request->query('lang')], 'is_string');

        return $query->paginate(10, page: $page, total: $total)->appends(array_filter($filters, fn ($value) => $value !== ''));
    }
}
