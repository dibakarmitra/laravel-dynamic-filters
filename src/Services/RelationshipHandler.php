<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class RelationshipHandler
{
    protected ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function handle(EloquentBuilder $query, string $relation, string $operator, $value, string $boolean = 'and'): void
    {
        if (!is_string($relation) || $relation === '') {
            throw new FilterException('Relation name must be a non-empty string');
        }

        if (!method_exists($query->getModel(), $relation)) {
            throw new FilterException(sprintf('Relationship method %s does not exist on model %s', 
                $relation, 
                get_class($query->getModel())
            ));
        }

        try {
            $query->whereHas($relation, function ($query) use ($operator, $value, $boolean) {
                $this->applyWhereClause($query, $operator, $value, $boolean);
            });
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to apply relationship filter: %s', $e->getMessage()), 0, $e);
        }
    }

    protected function applyWhereClause(EloquentBuilder $query, string $operator, $value, string $boolean = 'and'): void
    {
        if (!in_array(strtolower($boolean), ['and', 'or'], true)) {
            throw new FilterException("Invalid boolean operator: {$boolean}. Must be 'and' or 'or'");
        }

        $operator = strtolower($operator);
        
        if (in_array($operator, ['in', 'not in'], true) && is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if ($value === '' && in_array($operator, ['=', '!='])) {
            $value = null;
        }

        try {
            switch ($operator) {
                case '=':
                case '!=':
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $this->validateValue($value, $operator);
                    $query->where($this->getQualifiedKeyName($query), $operator, $value, $boolean);
                    break;
                    
                case 'like':
                case 'not like':
                case 'ilike':
                case 'not ilike':
                    if (!is_string($value)) {
                        throw new FilterException(sprintf('Operator %s requires a string value', $operator));
                    }
                    $query->where($this->getQualifiedKeyName($query), $operator, $value, $boolean);
                    break;
                    
                case 'in':
                    $this->validateArrayValue($value, $operator);
                    $query->whereIn($this->getQualifiedKeyName($query), (array) $value, $boolean);
                    break;
                    
                case 'not in':
                    $this->validateArrayValue($value, $operator);
                    $query->whereNotIn($this->getQualifiedKeyName($query), (array) $value, $boolean);
                    break;
                    
                case 'between':
                    $this->validateBetweenValue($value, $operator);
                    $query->whereBetween($this->getQualifiedKeyName($query), $value, $boolean);
                    break;
                    
                case 'not between':
                    $this->validateBetweenValue($value, $operator);
                    $query->whereNotBetween($this->getQualifiedKeyName($query), $value, $boolean);
                    break;
                    
                case 'null':
                    $query->whereNull($this->getQualifiedKeyName($query), $boolean);
                    break;
                    
                case 'not null':
                    $query->whereNotNull($this->getQualifiedKeyName($query), $boolean);
                    break;
                    
                default:
                    throw new FilterException(sprintf('Unsupported operator: %s', $operator));
            }
        } catch (\Exception $e) {
            throw new FilterException(sprintf('Failed to apply where clause: %s', $e->getMessage()), 0, $e);
        }
    }
    
    protected function getQualifiedKeyName(EloquentBuilder $query): string
    {
        return $query->getModel()->getQualifiedKeyName();
    }
    
    protected function validateValue($value, string $operator): void
    {
        if ($value === null && !in_array($operator, ['=', '!='], true)) {
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
