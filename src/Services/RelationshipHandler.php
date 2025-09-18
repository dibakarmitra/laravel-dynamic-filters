<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class RelationshipHandler
{
    protected ConnectionInterface $connection;
    protected array $config;

    public function __construct(ConnectionInterface $connection, array $config = [])
    {
        $this->connection = $connection;
        $this->config = $this->mergeDefaultConfig($config);
    }
    
    protected function mergeDefaultConfig(array $config): array
    {
        return array_merge([
            'operators' => [
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
                'date' => 'date',
                'year' => 'year',
                'month' => 'month',
                'day' => 'day',
                'time' => 'time',
            ],
            'global_whitelist' => [],
        ], $config);
    }

    public function handle(EloquentBuilder $query, string $relation, $conditions, string $boolean = 'and'): void
    {
        if (!is_string($relation) || $relation === '') {
            throw new FilterException('Relation name must be a non-empty string');
        }

        $model = $query->getModel();
        
        if (!method_exists($model, $relation)) {
            throw new FilterException(sprintf(
                'Relationship method %s does not exist on model %s', 
                $relation, 
                get_class($model)
            ));
        }

        try {
            $query->whereHas($relation, function ($query) use ($conditions, $relation, $model) {
                $relationQuery = $model->{$relation}();
                $related = $relationQuery->getRelated();
                
                if (is_array($conditions)) {
                    foreach ($conditions as $field => $value) {
                        if (is_array($value)) {
                            if (array_keys($value) === range(0, count($value) - 1)) {
                                if (count($value) === 2) {
                                    $query->where($field, ...$value);
                                }
                            } else {
                                foreach ($value as $operator => $val) {
                                    $this->applyWhereClause($query, $field, $operator, $val);
                                }
                            }
                        } else {
                            $query->where($field, '=', $value);
                        }
                    }
                } else {
                    $query->where($related->getQualifiedKeyName(), '=', $conditions);
                }
            });
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to apply relationship filter: %s', $e->getMessage()), 0, $e);
        }
    }

    public function applyNestedOrder(EloquentBuilder $query, string $relation, string $column, string $direction = 'asc'): void
    {
        try {
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
            
            $query->with([$relation => function ($query) use ($column, $direction) {
                $query->orderBy($column, $direction);
            }]);
            
            $relationInstance = $query->getModel()->{$relation}();
            $foreignKey = $relationInstance->getQualifiedForeignKeyName();
            $query->orderBy($foreignKey, $direction);
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to apply nested ordering: %s', $e->getMessage()), 0, $e);
        }
    }

    protected function applyNestedCondition(EloquentBuilder $query, string $field, $conditions, $relatedModel): void
    {
        if (is_array($conditions)) {
            foreach ($conditions as $operator => $value) {
                $this->applyWhereClause($query, $field, $operator, $value);
            }
        } else {
            $this->applyWhereClause($query, $field, '=', $conditions);
        }
    }
    
    protected function applyWhereClause(EloquentBuilder $query, string $field, string $operator, $value, string $boolean = 'and'): void
    {
        if (!in_array(strtolower($boolean), ['and', 'or'], true)) {
            throw new FilterException("Invalid boolean operator: {$boolean}");
        }
        
        $operators = $this->config['operators'] ?? [];
        $dbOperator = $operators[$operator] ?? $operator;
        $operator = strtolower($operator);
        
        if (is_string($value) && in_array($operator, ['in', 'not_in', 'not in'], true)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
            if (empty($value)) {
                return;
            }
        }

        try {
            switch ($operator) {
                case 'in':
                case 'in_array':
                    $this->validateArrayValue($value, $operator);
                    $query->whereIn($field, (array) $value, $boolean);
                    break;
                    
                case 'not_in':
                case 'not in':
                    $this->validateArrayValue($value, $operator);
                    $query->whereNotIn($field, (array) $value, $boolean);
                    break;
                    
                case 'between':
                    $this->validateBetweenValue($value, $operator);
                    $query->whereBetween($field, (array) $value, $boolean);
                    break;
                    
                case 'not_between':
                case 'not between':
                    $this->validateBetweenValue($value, $operator);
                    $query->whereNotBetween($field, (array) $value, $boolean);
                    break;
                    
                case 'null':
                case 'is_null':
                    $query->whereNull($field, $boolean);
                    break;
                    
                case 'not_null':
                case 'not null':
                    $query->whereNotNull($field, $boolean);
                    break;
                    
                case 'date':
                    if (is_string($value)) {
                        $query->whereDate($field, '=', $value, $boolean);
                    } elseif (is_array($value)) {
                        $query->whereDate($field, $value['operator'] ?? '=', $value['value'], $boolean);
                    }
                    break;
                    
                case 'where':
                    $query->where($field, ...(array) $value);
                    break;
                    
                default:
                    $query->where($field, $dbOperator, $value, $boolean);
            }
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to apply where clause: %s', $e->getMessage()), 0, $e);
        }
    }
    
    protected function validateValue($value, string $operator): void
    {
        if ($value === null && !in_array($operator, ['=', '!=', 'null', 'not_null'], true)) {
            throw new FilterException(sprintf('Operator %s does not accept null values', $operator));
        }
    }
    
    protected function validateArrayValue($value, string $operator): void
    {
        if (!is_array($value) || empty($value)) {
            throw new FilterException(sprintf('Operator %s requires a non-empty array', $operator));
        }
        
        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                throw new FilterException(sprintf('All values for operator %s must be scalar or null', $operator));
            }
        }
    }
    
    protected function validateBetweenValue($value, string $operator): void
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new FilterException(sprintf('Operator %s requires an array with exactly 2 values', $operator));
        }
        
        if ($value[0] === null || $value[1] === null) {
            throw new FilterException(sprintf('Operator %s does not accept null values', $operator));
        }
        
        if (!is_numeric($value[0]) || !is_numeric($value[1])) {
            throw new FilterException(sprintf('Operator %s requires numeric values', $operator));
        }
        
        if ($value[0] > $value[1]) {
            throw new FilterException(sprintf('First value must be less than or equal to second value for operator %s', $operator));
        }
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}