<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Illuminate\Database\Eloquent\Builder;

class FilterManager
{
    public function __construct(
        protected FilterParser $parser,
        protected SearchHandler $searchHandler,
        protected SortHandler $sortHandler,
        protected RelationshipHandler $relationshipHandler,
        protected array $config = []
    ) {}

    public function filter(Builder $query, array $filters = []): Builder
    {
        return $this->parser->apply($query, $filters);
    }

    public function search(Builder $query, string $term, array $searchable = [], ?string $mode = null): Builder
    {
        return $this->searchHandler->apply($query, $term, $searchable, $mode);
    }

    public function sort(Builder $query, string|array|null $sortParams = null, array $allowedColumns = []): Builder
    {
        return $this->sortHandler->apply($query, $sortParams, $allowedColumns);
    }

    public function getSearchHandler(): SearchHandler
    {
        return $this->searchHandler;
    }

    public function getSortHandler(): SortHandler
    {
        return $this->sortHandler;
    }

    public function getParser(): FilterParser
    {
        return $this->parser;
    }

    public function getRelationshipHandler(): RelationshipHandler
    {
        return $this->relationshipHandler;
    }

    public function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    public function setConfig(string $key, $value): void
    {
        data_set($this->config, $key, $value);
    }

    /**
     * Validate a search term against configured rules.
     *
     * @param string $term The search term to validate
     * @return array Array with 'valid' (bool) and 'message' (string) keys
     */
    public function validateSearchTerm(string $term): array
    {
        $term = trim($term);
        $minLength = $this->getConfig('search.min_term_length', 1);
        $blacklist = (array) $this->getConfig('search.blacklist', []);
        
        if ($term === '') {
            return [
                'valid' => false,
                'message' => 'Search term cannot be empty.'
            ];
        }
        
        if (mb_strlen($term) < $minLength) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Search term must be at least %d character(s) long. Current length: %d.',
                    $minLength,
                    mb_strlen($term)
                )
            ];
        }
        
        $normalizedTerm = strtolower($term);
        if (in_array($normalizedTerm, $blacklist, true)) {
            return [
                'valid' => false,
                'message' => 'The provided search term is not allowed.'
            ];
        }
        
        return ['valid' => true, 'message' => 'Search term is valid.'];
    }
    
    /**
     * Check if a search term is valid.
     * 
     * @deprecated Use validateSearchTerm() for detailed validation
     */
    public function isSearchTermValid(string $term): bool
    {
        return $this->validateSearchTerm($term)['valid'];
    }
}