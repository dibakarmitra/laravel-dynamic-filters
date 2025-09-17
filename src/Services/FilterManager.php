<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Illuminate\Database\Eloquent\Builder;

class FilterManager
{
    protected FilterParser $parser;
    protected SearchHandler $searchHandler;
    protected RelationshipHandler $relationshipHandler;
    protected array $config;

    public function __construct(
        FilterParser $parser,
        SearchHandler $searchHandler,
        RelationshipHandler $relationshipHandler,
        array $config = []
    ) {
        $this->parser = $parser;
        $this->searchHandler = $searchHandler;
        $this->relationshipHandler = $relationshipHandler;
        $this->config = $config;
    }

    public function apply(Builder $query, array $filters = []): Builder
    {
        return $this->parser->apply($query, $filters);
    }

    public function search(Builder $query, string $term, array $searchable = [], ?string $mode = null): Builder
    {
        return $this->searchHandler->apply($query, $term, $searchable, $mode);
    }

    public function getSearchHandler(): SearchHandler
    {
        return $this->searchHandler;
    }

    public function getParser(): FilterParser
    {
        return $this->parser;
    }

    public function getRelationshipHandler(): RelationshipHandler
    {
        return $this->relationshipHandler;
    }

    public function getSearchConfig(): array
    {
        return $this->config['search'] ?? [];
    }

    public function setSearchConfig(array $config): void
    {
        $this->config['search'] = array_merge($this->getSearchConfig(), $config);
    }

    public function getSearchableFields(): array
    {
        return $this->config['searchable_fields'] ?? [];
    }

    public function getBlacklistedTerms(): array
    {
        return $this->getSearchConfig()['blacklist'] ?? [];
    }

    public function isSearchTermValid(string $term): bool
    {
        $term = trim($term);
        $minLength = $this->getSearchConfig()['min_term_length'] ?? 1;
        
        return $term !== '' && 
            mb_strlen($term) >= $minLength && 
            !in_array(strtolower($term), $this->getBlacklistedTerms(), true);
    }

    public function getOperators(): array
    {
        return $this->config['operators'] ?? [];
    }

    public function getOperator(string $key): ?string
    {
        return $this->getOperators()[$key] ?? null;
    }

    public function hasOperator(string $key): bool
    {
        return isset($this->getOperators()[$key]);
    }

    public function getGlobalWhitelist(): array
    {
        return $this->config['global_whitelist'] ?? [];
    }

    public function getPaginationConfig(): array
    {
        return $this->config['pagination'] ?? [
            'per_page' => 10,
            'max_per_page' => 100,
            'page_name' => 'page',
        ];
    }

    public function getCustomFilters(): array
    {
        return $this->config['custom_filters'] ?? [];
    }

    public function getFilterPresets(): array
    {
        return $this->config['presets'] ?? [];
    }

    public function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    public function setConfig(string $key, $value): void
    {
        data_set($this->config, $key, $value);
    }
}
