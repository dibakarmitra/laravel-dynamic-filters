<?php

namespace Dibakar\LaravelDynamicFilters\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasDynamicFilter
{
    public function scopeFilter(Builder $query, array $filters = []): Builder
    {
        if (empty($filters)) {
            return $query;
        }

        return app('dynamic-filters.parser')->apply($query, $filters);
    }

    public function scopeSearch(Builder $query, ?string $term = null): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return app('dynamic-filters.search')->apply($query, $term, $this->searchable ?? []);
    }

    public function scopeSort(Builder $query, string|array|null $sort = null): Builder
    {
        if (empty($sort)) {
            return $query;
        }

        $sortParams = is_string($sort) ? explode(';', $sort) : (array) $sort;
        
        return app('dynamic-filters.sort')->apply($query, $sortParams, $this->sortable ?? []);
    }

    public function getFilterable(): array
    {
        if (method_exists($this, 'filterable')) {
            return $this->filterable();
        }
        
        return $this->filterable ?? [];
    }

    public function getSearchable(): array
    {
        return $this->searchable ?? [];
    }

    public function getSortable(): array
    {
        return $this->sortable ?? [];
    }
}