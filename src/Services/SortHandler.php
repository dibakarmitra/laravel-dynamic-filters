<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;
use Illuminate\Database\Eloquent\Builder;

class SortHandler
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'direction_indicators' => ['+' => 'asc', '-' => 'desc'],
            'default_direction' => 'asc',
            'default_sort' => null,
        ], $config);
    }

    public function apply(Builder $query, string|array|null $sortParams = null, array $allowedColumns = []): Builder
    {
        $sortParams = $sortParams ?? $this->config['default_sort'];
        if (empty($sortParams)) {
            return $query;
        }

        foreach ((array) $sortParams as $sort) {
            $this->applySort($query, $sort, $allowedColumns);
        }
        
        return $query;
    }

    protected function applySort(Builder $query, string $sort, array $allowedColumns): void
    {
        if (empty(trim($sort))) {
            return;
        }

        [$column, $direction] = $this->parseSortParameter($sort);
        
        if (empty($column)) {
            throw new FilterException('Sort column cannot be empty.');
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new FilterException("Invalid sort direction: {$direction}. Must be one of: asc, desc");
        }

        if (!empty($allowedColumns)) {
            $allowedKeys = array_keys($allowedColumns);
            $isAssoc = array_keys($allowedKeys) !== $allowedKeys;
            $allowedList = $isAssoc ? $allowedKeys : $allowedColumns;
            
            if (!in_array($column, $allowedList, true)) {
                throw new FilterException("Sorting by column '{$column}' is not allowed. Allowed columns: " . implode(', ', $allowedList));
            }
        }

        $this->applyOrderBy($query, $column, $direction, $allowedColumns);
    }

    protected function parseSortParameter(string $sort): array
    {
        if (trim($sort) === '') {
            throw new FilterException('Sort parameter cannot be empty');
        }

        $firstChar = $sort[0];
        $lastChar = substr($sort, -1);
        
        if (isset($this->config['direction_indicators'][$firstChar])) {
            $column = trim(substr($sort, 1));
            if ($column === '') {
                throw new FilterException('Column name cannot be empty after direction indicator');
            }
            return [
                $column,
                $this->config['direction_indicators'][$firstChar]
            ];
        }
        
        if (isset($this->config['direction_indicators'][$lastChar])) {
            $column = trim(substr($sort, 0, -1));
            if ($column === '') {
                throw new FilterException('Column name cannot be empty before direction indicator');
            }
            return [
                $column,
                $this->config['direction_indicators'][$lastChar]
            ];
        }
        
        $parts = array_map('trim', explode(',', $sort, 2));
        $column = $parts[0] ?? '';
        if ($column === '') {
            throw new FilterException('Column name cannot be empty');
        }
        
        $direction = strtolower($parts[1] ?? $this->config['default_direction']);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new FilterException('Invalid sort direction. Must be one of: asc, desc');
        }
        
        return [$column, $direction];
    }

    protected function applyOrderBy(Builder $query, string $column, string $direction, array $allowedColumns): void
    {
        if (!is_string($column) || $column === '') {
            throw new FilterException('Column name must be a non-empty string');
        }

        if (!in_array(strtoupper($direction), ['ASC', 'DESC'], true)) {
            throw new FilterException('Sort direction must be either ASC or DESC');
        }

        if (isset($allowedColumns[$column]) && is_callable($allowedColumns[$column])) {
            $allowedColumns[$column]($query, $direction);
            return;
        }

        if (str_contains($column, '.')) {
            $this->applyNestedOrderBy($query, $column, $direction);
            return;
        }

        $query->orderBy($column, $direction);
    }

    protected function applyNestedOrderBy(Builder $query, string $column, string $direction): void
    {
        $parts = explode('.', $column);
        $column = array_pop($parts);
        $relation = implode('.', $parts);
        
        $query->with([
            $relation => fn($q) => $q->orderBy($column, $direction)
        ]);
        
        $relationModel = $query->getModel()->{$relation}();
        $query->orderBy($relationModel->getQualifiedForeignKeyName(), $direction);
    }

    public function setDefaultSort($sort): self
    {
        $this->config['default_sort'] = $sort;
        return $this;
    }

    public function setDirectionIndicator(string $indicator, string $direction): self
    {
        $this->config['direction_indicators'][$indicator] = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        return $this;
    }
}