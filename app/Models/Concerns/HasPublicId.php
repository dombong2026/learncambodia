<?php

namespace App\Models\Concerns;

use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;

trait HasPublicId
{
    protected function performInsert(Builder $query)
    {
        $connection = $query->getConnection();

        return $connection->transaction(function () use ($connection, $query) {
            $this->public_id = PublicId::allocate($connection, $this->publicIdDigits());

            return parent::performInsert($query);
        });
    }

    private function publicIdDigits(): int
    {
        return 6;
    }

    public function getRouteKey()
    {
        return $this->public_id && $this->title
            ? PublicId::path($this->public_id, $this->title)
            : parent::getRouteKey();
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (($field === null || $field === 'slug') && ($id = PublicId::fromPath($value, $this->publicIdDigits()))) {
            if ($field === 'slug' && !$this->newQuery()->where('public_id', $id)->exists()) {
                return $query->where('slug', $value);
            }

            return $query->where($this->qualifyColumn('public_id'), $id);
        }

        if ($field === null && !ctype_digit((string) $value)) {
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
