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
}
