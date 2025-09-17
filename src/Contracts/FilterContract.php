<?php

namespace Dibakar\LaravelDynamicFilters\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface FilterContract
{
    /**
     * Apply the filter to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @param  string  $property
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $query, $value, string $property): Builder;

    /**
     * Validate the filter value.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function validate($value): bool;
}
