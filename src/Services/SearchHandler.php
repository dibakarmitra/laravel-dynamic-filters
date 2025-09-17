<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SearchHandler
{
    protected array $config;

    protected array $defaults = [
        'min_term_length' => 2,
        'max_terms' => 5,
        'blacklist' => ['the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for'],
        'mode' => 'or',
        'case_sensitive' => false,
    ];
    
    protected const DEFAULT_LIKE_OPERATOR = 'like';
    protected const POSTGRES_LIKE_OPERATOR = 'ilike';

    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->config = array_merge($this->defaults, $config);
    }

    protected function validateConfig(array $config): void
    {
        if (isset($config['min_term_length']) && !is_int($config['min_term_length'])) {
            throw new FilterException('min_term_length must be an integer');
        }
        
        if (isset($config['max_terms']) && !is_int($config['max_terms'])) {
            throw new FilterException('max_terms must be an integer');
        }
        
        if (isset($config['blacklist']) && !is_array($config['blacklist'])) {
            throw new FilterException('blacklist must be an array');
        }
    }

    public function apply(Builder $query, string $term, array $searchable = [], ?string $mode = null): Builder
    {
        if (empty($searchable)) {
            throw new FilterException('No searchable fields provided');
        }
        
        $term = trim($term);
        if ($term === '') {
            return $query;
        }
        
        try {
            $terms = $this->prepareSearchTerms($term);
            if (empty($terms)) {
                return $query;
            }

            $mode = $mode ? strtolower($mode) : $this->config['mode'];
            if (!in_array($mode, ['and', 'or'], true)) {
                throw new FilterException("Invalid search mode: {$mode}. Must be 'and' or 'or'");
            }
            
            $boolean = $mode === 'and' ? 'and' : 'or';

            return $query->where(function ($query) use ($terms, $searchable, $boolean) {
                foreach ($terms as $searchTerm) {
                    $query->where(function ($q) use ($searchTerm, $searchable) {
                        foreach ($searchable as $column) {
                            if (!is_string($column) || $column === '') {
                                throw new FilterException('Searchable fields must be non-empty strings');
                            }
                            $this->addSearchClause($q, $column, $searchTerm, 'or');
                        }
                    }, null, null, $boolean);
                }
            });
        } catch (\Exception $e) {
            throw new FilterException('Search failed: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function addSearchClause(Builder $query, string $column, string $term, string $boolean = 'and'): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $column)) {
            throw new FilterException(sprintf('Invalid column name: %s', $column));
        }
        
        $likeOperator = $this->getLikeOperator($query);
        $searchTerm = $this->getSearchTerm($term, $likeOperator);
        
        if ($searchTerm === '') {
            return;
        }
        
        try {
            if (Str::contains($column, '.')) {
                $this->addNestedSearchClause($query, $column, $searchTerm, $boolean);
                return;
            }

            $query->where(
                $query->getQuery()->getGrammar()->wrap($column),
                $likeOperator,
                $searchTerm,
                $boolean
            );
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to add search clause for column %s: %s', $column, $e->getMessage()), 0, $e);
        }
    }

    protected function addNestedSearchClause(Builder $query, string $column, string $term, string $boolean): void
    {
        $parts = explode('.', $column);
        if (count($parts) < 2) {
            throw new FilterException(sprintf('Invalid nested column format: %s', $column));
        }
        
        $relation = $parts[0];
        $nestedColumn = $parts[1];
        
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $nestedColumn)) {
            throw new FilterException(sprintf('Invalid nested column name: %s', $nestedColumn));
        }
        
        $likeOperator = $this->getLikeOperator($query);
        $searchTerm = $this->getSearchTerm($term, $likeOperator);
        
        try {
            $query->orWhereHas($relation, function ($q) use ($nestedColumn, $likeOperator, $searchTerm) {
                $q->where(
                    $nestedColumn,
                    $likeOperator,
                    $searchTerm
                );
            });
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to add nested search for %s: %s', $column, $e->getMessage()), 0, $e);
        }
    }

    protected function getLikeOperator(Builder $query): string
    {
        static $driverCache = [];
        $connection = $query->getQuery()->getConnection();
        $connectionName = get_class($connection);
            
        if (isset($driverCache[$connectionName])) {
            return $driverCache[$connectionName];
        }
        
        $driver = null;
        
        if (method_exists($connection, 'getDriverName')) {
            /** @var array|mixed $connection */
            $driver = $connection->getDriverName();
        }
        
        if (!$driver && method_exists($connection, 'getConfig')) {
            /** @var array|mixed $connection */
            $config = $connection->getConfig();
            $driver = is_array($config) ? ($config['driver'] ?? null) : null;
        }

        if (!$driver) {
            $connectionClass = get_class($connection);
            if (stripos($connectionClass, 'Postgres') !== false) {
                $driver = 'pgsql';
            } elseif (stripos($connectionClass, 'MySql') !== false) {
                $driver = 'mysql';
            } elseif (stripos($connectionClass, 'SQLite') !== false) {
                $driver = 'sqlite';
            } else {
                $driver = 'unknown';
            }
        }
        
        $operator = (is_string($driver) && stripos($driver, 'pgsql') !== false)
            ? self::POSTGRES_LIKE_OPERATOR
            : self::DEFAULT_LIKE_OPERATOR;

        $driverCache[$connectionName] = $operator;
        
        return $operator;
    }

    protected function prepareSearchTerms(string $term): array
    {
        $term = $this->normalizeTerm($term);
        
        preg_match_all('/"([^"]+)"|(\S+)/', $term, $matches);
        
        $terms = array_map(function ($term) {
            return trim($term, '\'"');
        }, $matches[0]);
        
        $terms = array_filter($terms, function ($term) {
            $term = trim($term);
            
            if ($term === '') {
                return false;
            }
            
            $minLength = $this->config['min_term_length'];
            if (mb_strlen($term) < $minLength) {
                throw new FilterException(sprintf('Search term "%s" is too short. Minimum length is %d characters.', $term, $minLength));
            }
            
            if (in_array(strtolower($term), $this->config['blacklist'], true)) {
                return false;
            }
            
            return true;
        });
        
        $maxTerms = $this->config['max_terms'];
        if (count($terms) > $maxTerms) {
            $terms = array_slice($terms, 0, $maxTerms);
        }
        
        return array_values($terms);
    }

    protected function normalizeTerm(string $term): string
    {
        if (empty($this->config['case_sensitive'])) {
            $term = mb_strtolower($term, 'UTF-8');
        }
        
        if (function_exists('iconv')) {
            $term = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $term);
        }
        
        return trim(preg_replace('/\s+/', ' ', $term));
    }

    protected function getSearchTerm(string $term, string $operator): string
    {
        if ($term === '') {
            throw new FilterException('Search term cannot be empty');
        }
        
        try {
            $term = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                $term
            );
            
            if (!Str::contains($term, ['%', '_'])) {
                $term = "%{$term}%";
            }
            
            return $term;
        } catch (\Exception $e) {
            throw new FilterException('Failed to prepare search term: ' . $e->getMessage(), 0, $e);
        }
    }
}
