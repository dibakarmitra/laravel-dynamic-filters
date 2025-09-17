<?php

namespace Dibakar\LaravelDynamicFilters\Services;

use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class FilterParser
{
    protected mixed $connection;
    protected array $config;

    public function __construct(mixed $connection, array $config = [])
    {
        $this->connection = $connection;
        $this->config = $config;
    }

    public function apply(EloquentBuilder $query, array $filters = []): EloquentBuilder
    {
        if (empty($filters)) {
            return $query;
        }

        if (!is_array($filters)) {
            throw new FilterException('Filters must be an array');
        }

        try {
            return $query->where(function ($query) use ($filters) {
                foreach ($filters as $field => $value) {
                    if (!is_string($field)) {
                        throw new FilterException(sprintf('Filter field must be a string, %s given', gettype($field)));
                    }

                    if (is_array($value)) {
                        $this->applyFilterGroup($query, $field, $value);
                    } else {
                        $this->applyBasicFilter($query, $field, $value);
                    }
                }
            });
        } catch (\Exception $e) {
            throw new FilterException('Failed to apply filters: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function applyBasicFilter(EloquentBuilder $query, string $field, $value): void
    {
        if (!is_string($field) || $field === '') {
            throw new FilterException('Field name must be a non-empty string');
        }

        $whitelist = $this->config['global_whitelist'] ?? [];
        if (!empty($whitelist) && !in_array($field, $whitelist, true)) {
            throw new FilterException(sprintf('Field "%s" is not filterable', $field));
        }

        $operator = '=';
        
        if (is_array($value)) {
            $operator = 'in';
            $value = array_filter($value, function($item) {
                return !is_null($item) && $item !== '';
            });
            
            if (empty($value)) {
                return;
            }
        } elseif (is_string($value)) {
                if (preg_match('/^(>=|<=|!=|>|<|~)/', $value, $matches)) {
                $operator = $matches[0];
                $value = substr($value, strlen($operator));
            }
            
            $value = trim($value);
            if ($value === '') {
                return;
            }
        }
        
        $model = $query->getModel();
        $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];
        if (isset($casts[$field])) {
            $value = $this->castValue($value, $casts[$field]);
        }

        $query->where($field, $this->getOperator($operator), $value);
    }
    
    protected function applyFilterGroup(EloquentBuilder $query, string $groupName, array $filters): void
    {
        if (!is_array($filters)) {
            throw new FilterException('Filter group must be an array');
        }

        $boolean = strtolower($groupName) === 'or' ? 'or' : 'and';
        
        try {
            $query->where(function (EloquentBuilder $query) use ($filters) {
                foreach ($filters as $field => $value) {
                    if (!is_string($field) && !is_int($field)) {
                        throw new FilterException('Filter field must be a string or integer');
                    }

                    if (is_array($value)) {
                        $this->applyFilterGroup($query, (string) $field, $value);
                    } else {
                        $this->applyBasicFilter($query, (string) $field, $value);
                    }
                }
            }, null, null, $boolean);
        } catch (\Exception $e) {
            throw new FilterException('Failed to apply filter group: ' . $e->getMessage(), 0, $e);
        }
    }
    
    protected function castValue(mixed $value, string $type): mixed
    {
        if (is_null($value)) {
            return null;
        }
        
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                }
                return (array) $value;
            case 'date':
            case 'datetime':
                return is_numeric($value) ? (int) $value : $value;
            default:
                return $value;
        }
    }
    
    protected function getOperator(string $symbol): string
    {
        static $operators = [
            '=' => '=',
            '!=' => '!=',
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            '~' => 'like',
            '!~' => 'not like',
            'in' => 'in',
            'not_in' => 'not in',
            'between' => 'between',
            'not_between' => 'not between',
            'null' => 'null',
            'not_null' => 'not null',
        ];
        
        $customOperators = $this->config['operators'] ?? [];
        $allOperators = array_merge($operators, $customOperators);
        
        if (!array_key_exists($symbol, $allOperators)) {
            throw new FilterException(sprintf('Unsupported operator: %s', $symbol));
        }
        
        return $allOperators[$symbol];
    }
}
