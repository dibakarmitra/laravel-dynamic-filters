<?php

namespace Dibakar\LaravelDynamicFilters\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Eloquent\Builder;
use Dibakar\LaravelDynamicFilters\Services\{
    FilterParser,
    SearchHandler,
    RelationshipHandler
};

/**
 * Facade for accessing the Dynamic Filters functionality.
 *
 * Provides a convenient interface for applying filters and searches to Eloquent queries.
 * 
 * @method static Builder apply(Builder $query, array $filters = []) Apply filters to a query
 * @method static FilterParser getParser() Get the filter parser instance
 * @method static SearchHandler getSearchHandler() Get the search handler instance
 * @method static RelationshipHandler getRelationshipHandler() Get the relationship handler instance
 * @method static Builder search(Builder $query, string $term, array $searchable = [], ?string $mode = null) Apply search to a query
 * @method static array getSearchConfig() Get the current search configuration
 * @method static void setSearchConfig(array $config) Update the search configuration
 * @method static array getSearchableFields() Get the list of searchable fields
 * @method static array getBlacklistedTerms() Get the list of blacklisted search terms
 * @method static bool isSearchTermValid(string $term) Check if a search term is valid
 * @method static array getOperators() Get all available operators
 * @method static string getOperator(string $key) Get a specific operator by key
 * @method static bool hasOperator(string $key) Check if an operator exists
 * @method static array getGlobalWhitelist() Get the global whitelist of filterable fields
 * @method static array getPaginationConfig() Get the pagination configuration
 * @method static array getCustomFilters() Get the custom filter classes
 * @method static array getFilterPresets() Get the filter presets
 * @method static mixed getConfig(string $key, mixed $default = null) Get a configuration value
 * @method static void setConfig(string $key, mixed $value) Set a configuration value
 * 
 * @see \Dibakar\LaravelDynamicFilters\Services\FilterManager
 * @see \Dibakar\LaravelDynamicFilters\Traits\HasDynamicFilter
 * 
 * @package Dibakar\LaravelDynamicFilters\Facades
 */
class DynamicFilter extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'dynamic-filters';
    }
}