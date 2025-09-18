<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class FilterParser
{
    public function __construct(
        protected mixed $connection,
        protected array $config = [],
        protected ?RelationshipHandler $relationshipHandler = null
    ) {
        if ($this->connection === null) {
            throw new \InvalidArgumentException('Database connection cannot be null');
        }
        $this->relationshipHandler = $relationshipHandler ?? app(RelationshipHandler::class);
    }

    public function apply(EloquentBuilder $query, array $filters = []): EloquentBuilder
    {
        if (empty($filters)) {
            return $query;
        }

        if (!is_array($filters)) {
            $type = is_object($filters) ? get_class($filters) : gettype($filters);
            throw new FilterException(sprintf(
                'Filters must be an array, %s given. Please provide filters as an associative array.',
                $type
            ));
        }

        $maxFilters = $this->config['max_filters'] ?? 50;
        if (count($filters) > $maxFilters) {
            throw new FilterException(sprintf(
                'Too many filters specified. Maximum allowed: %d, received: %d',
                $maxFilters,
                count($filters)
            ));
        }

        $ignoredParams = ['page', 'per_page', 'sort', 'search'];
        $filters = array_diff_key($filters, array_flip($ignoredParams));

        try {
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    foreach ($value as $operator => $operatorValue) {
                        $this->applyFilter($query, $field, $operator, $operatorValue);
                    }
                } else {
                    $this->applyFilter($query, $field, 'eq', $value);
                }
            }
            return $query;
        } catch (\Exception $e) {
            $context = [
                'field' => $field ?? null,
                'operator' => $operator ?? null,
                'value_type' => isset($value) ? gettype($value) : null,
            ];
            
            throw new FilterException(sprintf(
                'Failed to apply filter%s: %s. Context: %s',
                isset($field) ? ' for field "' . $field . '"' : '',
                $e->getMessage(),
                json_encode($context, JSON_PRETTY_PRINT)
            ), $e->getCode(), $e);
        }
    }

    protected function applyFilter(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        if (!is_string($field) || $field === '') {
            $type = is_object($field) ? get_class($field) : gettype($field);
            throw new FilterException(sprintf(
                'Field name must be a non-empty string, %s given',
                $type === 'string' ? 'empty string' : $type
            ));
        }

        $operators = $this->getSupportedOperators();
        
        if (!isset($operators[$operator])) {
            $supportedOps = array_keys($operators);
            throw new FilterException(sprintf(
                'Unsupported operator: "%s". Supported operators are: %s',
                $operator,
                implode(', ', array_map(fn($op) => '"' . $op . '"', $supportedOps))
            ));
        }

        if (str_contains($field, '.')) {
            $this->applyRelationshipFilter($query, $field, $operator, $value);
            return;
        }

        $dbOperator = $operators[$operator];
        
        if (in_array($operator, [
            'today', 'yesterday', 'this_week', 'last_week',
            'this_month', 'last_month', 'this_year', 'last_year',
            'last_x_days', 'next_x_days'
        ])) {
            $this->applyDateFilter($query, $field, $operator, $value);
            return;
        }
        
        switch ($operator) {
            case 'in':
            case 'not_in':
                $this->applyArrayFilter($query, $field, $operator, (array) $value);
                break;
                
            case 'between':
            case 'not_between':
                $this->applyBetweenFilter($query, $field, $operator, $value);
                break;
                
            case 'null':
            case 'not_null':
                $query->{$operator === 'null' ? 'whereNull' : 'whereNotNull'}($field);
                break;
                
            case 'like':
            case 'not_like':
                $this->applyLikeFilter($query, $field, $operator, $value);
                break;
                
            case 'starts_with':
            case 'ends_with':
            case 'contains':
                $this->applyPatternFilter($query, $field, $operator, $value);
                break;
                
            default:
                $query->where($field, $dbOperator, $value);
        }
    }

    protected function applyRelationshipFilter(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        $parts = explode('.', $field);
        $relation = array_shift($parts);
        $nestedField = implode('.', $parts);

        if (count($parts) > 1) {
            $nestedRelation = array_shift($parts);
            $nestedField = implode('.', $parts);
            
            $query->whereHas("$relation.$nestedRelation", function($q) use ($nestedField, $operator, $value) {
                $this->applyFilterCondition($q, $nestedField, $operator, $value);
            });
        } else {
            $query->whereHas($relation, function($q) use ($nestedField, $operator, $value) {
                $this->applyFilterCondition($q, $nestedField, $operator, $value);
            });
        }
    }

    protected function applyFilterCondition(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        $operators = $this->getSupportedOperators();
        $dbOperator = $operators[$operator] ?? '=';

        switch ($operator) {
            case 'in':
            case 'not_in':
                $this->applyArrayFilter($query, $field, $operator, (array) $value);
                break;
                
            case 'between':
            case 'not_between':
                $this->applyBetweenFilter($query, $field, $operator, $value);
                break;
                
            case 'null':
            case 'not_null':
                $query->{$operator === 'null' ? 'whereNull' : 'whereNotNull'}($field);
                break;
                
            case 'like':
            case 'not_like':
                $this->applyLikeFilter($query, $field, $operator, $value);
                break;
                
            case 'starts_with':
            case 'ends_with':
            case 'contains':
                $this->applyPatternFilter($query, $field, $operator, $value);
                break;
                
            case 'date':
            case 'year':
            case 'month':
            case 'day':
            case 'time':
                $this->applyDateFilter($query, $field, $operator, $value, $dbOperator);
                break;
                
            default:
                $query->where($field, $dbOperator, $value);
        }
    }

    protected function applyArrayFilter(EloquentBuilder $query, string $field, string $operator, array $value): void
    {
        if (empty($value)) return;
        $method = $operator === 'not_in' ? 'whereNotIn' : 'whereIn';
        $query->$method($field, $value);
    }

    protected function applyBetweenFilter(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new FilterException("The '{$operator}' operator requires an array with exactly 2 values");
        }

        $method = $operator === 'between' ? 'whereBetween' : 'whereNotBetween';
        $query->{$method}($field, $value);
    }

    protected function applyDateFilter(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        $date = now($this->config['date']['timezone'] ?? 'UTC');
        $format = $this->config['date']['format'] ?? 'Y-m-d H:i:s';
        
        $range = match($operator) {
            'today' => [
                $date->copy()->startOfDay()->format($format),
                $date->copy()->endOfDay()->format($format)
            ],
            'yesterday' => [
                $date->copy()->subDay()->startOfDay()->format($format),
                $date->copy()->subDay()->endOfDay()->format($format)
            ],
            'this_week' => [
                $date->copy()->startOfWeek()->format($format),
                $date->copy()->endOfWeek()->format($format)
            ],
            'last_week' => [
                $date->copy()->subWeek()->startOfWeek()->format($format),
                $date->copy()->subWeek()->endOfWeek()->format($format)
            ],
            'this_month' => [
                $date->copy()->startOfMonth()->format($format),
                $date->copy()->endOfMonth()->format($format)
            ],
            'last_month' => [
                $date->copy()->subMonth()->startOfMonth()->format($format),
                $date->copy()->subMonth()->endOfMonth()->format($format)
            ],
            'this_year' => [
                $date->copy()->startOfYear()->format($format),
                $date->copy()->endOfYear()->format($format)
            ],
            'last_year' => [
                $date->copy()->subYear()->startOfYear()->format($format),
                $date->copy()->subYear()->endOfYear()->format($format)
            ],
            'last_x_days' => [
                $date->copy()->subDays((int)$value)->format($format),
                $date->copy()->format($format)
            ],
            'next_x_days' => [
                $date->copy()->format($format),
                $date->copy()->addDays((int)$value)->format($format)
            ],
            default => null
        };

        if ($range) {
            $query->whereBetween($field, $range);
        } else {
            throw new FilterException("Unsupported date operator: {$operator}");
        }
    }

    protected function applyLikeFilter(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        $value = $this->prepareLikeValue($value);
        $method = $operator === 'not_like' ? 'whereNot' : 'where';
        $query->$method($field, 'like', $value);
    }

    protected function applyPatternFilter(EloquentBuilder $query, string $field, string $operator, $value): void
    {
        $value = match($operator) {
            'starts_with' => $value . '%',
            'ends_with' => '%' . $value,
            'contains' => '%' . $value . '%',
            default => $value
        };
        $query->where($field, 'like', $value);
    }

    protected function prepareLikeValue($value): string
    {
        return is_string($value) && !str_contains($value, '%') 
            ? "%{$value}%" 
            : (string) $value;
    }

    protected function getSupportedOperators(): array
    {
        return [
            'eq' => '=',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'like',
            'not_like' => 'not like',
            'in' => 'in',
            'not_in' => 'not in',
            'between' => 'between',
            'not_between' => 'not between',
            'null' => 'null',
            'not_null' => 'not null',
            'starts_with' => 'like',
            'ends_with' => 'like',
            'contains' => 'like',
            'today' => 'date',
            'yesterday' => 'date',
            'this_week' => 'date',
            'last_week' => 'date',
            'this_month' => 'date',
            'last_month' => 'date',
            'this_year' => 'date',
            'last_year' => 'date',
            'last_x_days' => 'date',
            'next_x_days' => 'date',
        ];
    }
}