# Laravel Dynamic Filters - API Documentation

A powerful Laravel package for dynamic filtering, searching, and sorting of Eloquent models with support for relationships, custom filters, and more.

## Table of Contents
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Configuration](#configuration)
- [Facade Methods](#facade-methods)
  - [Filtering](#filtering-methods)
  - [Search](#search-methods)
  - [Sorting](#sorting-methods)
  - [Configuration](#configuration-methods)
- [Filter Types](#filter-types)
  - [Basic Filtering](#basic-filtering)
  - [Operators](#operators)
  - [Search](#search)
  - [Sorting](#sorting)
- [Custom Filters](#custom-filters)
  - [Creating Custom Filters](#creating-custom-filters)
  - [Registering Custom Filters](#registering-custom-filters)
- [Advanced Usage](#advanced-usage)
  - [Relationship Filtering](#relationship-filtering)
  - [Filter Presets](#filter-presets)
  - [Pagination](#pagination)
  - [Security](#security)
- [Example Filters](#example-filters)
  - [Date Range Filter](#date-range-filter)
  - [Tag Filter](#tag-filter)
  - [Full-Text Search](#full-text-search)
  - [Published in Last X Days](#published-in-last-x-days)
- [Error Handling](#error-handling)
- [Performance Tips](#performance-tips)

## Installation

```bash
composer require dibakar/laravel-dynamic-filters
```

### Publish Configuration (Optional)

Publish the configuration file to customize the package behavior:

```bash
php artisan vendor:publish --provider="Dibakar\LaravelDynamicFilters\DynamicFiltersServiceProvider" --tag="config"
```

This will create a `dynamic-filters.php` file in your `config` directory.

### Service Provider

The package will automatically register its service provider using Laravel's package discovery.

## Basic Usage

### Model Setup

Add the `HasDynamicFilter` trait to your model and define filterable/searchable fields:

```php
use Dibakar\LaravelDynamicFilters\Traits\HasDynamicFilter;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasDynamicFilter;
    
    /**
     * Fields that can be filtered
     */
    protected $filterable = [
        'title',
        'status',
        'created_at',
        'category_id',
        'views',
        'is_featured',
    ];
    
    /**
     * Fields that can be searched
     */
    protected $searchable = [
        'title',
        'content',
        'author.name', // Supports relationship searching
    ];
    
    /**
     * Default sorting
     */
    protected $sortable = [
        'created_at' => 'desc',
        'title' => 'asc',
    ];
}
```

### Basic Filtering

```php
// In your controller
public function index(Request $request)
{
    $posts = Post::filter($request->all())->get();
    return response()->json($posts);
}
```

### Basic Search

```php
// Search in searchable fields
$posts = Post::search('search term')->get();

// Or with specific fields
$posts = Post::search('search term', ['title', 'content'])->get();
```

## Configuration

### Security Settings

```php
'security' => [
    // Maximum number of filters allowed in a single request
    'max_filters' => 50,
    
    // Maximum depth for nested relationships
    'max_nesting_level' => 5,
    
    // Log filter queries (for debugging and auditing)
    'log_queries' => env('DYNAMIC_FILTERS_LOG_QUERIES', false),
],
```

### Operators

```php
'operators' => [
    'eq'           => '=',
    'neq'          => '!=',
    'gt'           => '>',
    'gte'          => '>=',
    'lt'           => '<',
    'lte'          => '<=',
    'like'         => 'like',
    'not_like'     => 'not like',
    'ilike'        => 'ilike',
    'not_ilike'    => 'not ilike',
    'starts_with'  => 'like',
    'ends_with'    => 'like',
    'contains'     => 'like',
    'in'           => 'in',
    'not_in'       => 'not in',
    'between'      => 'between',
    'not_between'  => 'not between',
    'null'         => 'null',
    'not_null'     => 'not null',
    'date'         => 'date',
    'year'         => 'year',
    'month'        => 'month',
    'day'          => 'day',
    'time'         => 'time',
],
```

### Search Configuration

```php
'search' => [
    // The request parameter name for search terms
    'param' => 'search',
    
    // Maximum search term length
    'max_length' => 255,
    
    // Minimum search term length
    'min_length' => 2,
    
    // Enable fuzzy search
    'fuzzy' => false,
    
    // Search mode: 'and' or 'or'
    'mode' => 'or',
    
    // Case sensitive search
    'case_sensitive' => false,
],
```

## Facade Methods

### Filtering Methods

#### `apply(Builder $query, array $filters = []): Builder`
Applies filters to an Eloquent query.

```php
use Dibakar\LaravelDynamicFilters\Facades\DynamicFilter;

// Basic equality filter
$posts = DynamicFilter::filter(Post::query(), [
    'status' => 'published',
])->get();

// Using operators
$posts = DynamicFilter::filter(Post::query(), [
    'views' => ['gt' => 100],
    'created_at' => [
        'gte' => '2023-01-01',
        'lte' => '2023-12-31'
    ]
])->get();

// Multiple conditions with OR
$posts = DynamicFilter::filter(Post::query(), [
    'or' => [
        ['status' => 'published'],
        ['is_featured' => true]
    ]
])->get();
```

#### `getParser(): FilterParser`
Gets the filter parser instance.

```php
$parser = DynamicFilter::getParser();
```

### Search Methods

#### `search(Builder $query, string $term, array $searchable = [], ?string $mode = null): Builder`
Performs a search on the query.

```php
// Basic search
$results = DynamicFilter::search(Post::query(), 'search term')->get();

// Search with specific fields
$results = DynamicFilter::search(
    Post::query(), 
    'search term', 
    ['title', 'content', 'author.name'],
    'and' // 'and' or 'or' mode
)->get();
```

#### `validateSearchTerm(string $term): array`
Validates a search term against configured rules.

```php
$validation = DynamicFilter::validateSearchTerm('test');
if (!$validation['valid']) {
    return response()->json(['error' => $validation['message']], 400);
}
```

### Sorting Methods

#### `sort(Builder $query, string|array|null $sortParams = null, array $allowedColumns = []): Builder`
Applies sorting to the query. Supports multiple sort formats for flexibility.

```php
// Basic usage
$posts = DynamicFilter::sort(Post::query(), 'title,desc')->get();

// Alternative format with prefix
$posts = DynamicFilter::sort(Post::query(), '-created_at')->get();

// Multiple sort fields
$posts = DynamicFilter::sort(Post::query(), [
    'created_at,desc',
    'title,asc'
])->get();

// With allowed columns for security
$posts = DynamicFilter::sort(
    Post::query(), 
    $request->input('sort'), 
    ['created_at', 'title', 'views', 'author.name']
)->get();
```

### Configuration Methods

#### `getConfig(string $key, $default = null)`
Gets a configuration value.

```php
$minTermLength = DynamicFilter::getConfig('search.min_term_length', 2);
```

#### `setConfig(string $key, $value): void`
Sets a configuration value at runtime.

```php
DynamicFilter::setConfig('search.case_sensitive', true);
```

#### `getSearchConfig(): array`
Gets the current search configuration.

```php
$config = DynamicFilter::getSearchConfig();
```
```php
$config = DynamicFilter::getSearchConfig();
```

#### `setSearchConfig(array $config): void`
Updates the search configuration.

```php
DynamicFilter::setSearchConfig([
    'min_term_length' => 3,
    'blacklist' => ['and', 'or', 'the']
]);
```

#### `getSearchableFields(): array`
Gets the list of searchable fields.

```php
$searchable = DynamicFilter::getSearchableFields();
```

#### `getBlacklistedTerms(): array`
Gets the list of blacklisted search terms.

```php
$blacklist = DynamicFilter::getBlacklistedTerms();
```

#### `validateSearchTerm(string $term): array`
Validates a search term and returns detailed validation results.

```php
$result = DynamicFilter::validateSearchTerm('search term');
if ($result['valid']) {
    // Process search
} else {
    // Show error message: $result['message']
}

// Example response:
// [
//     'valid' => true,
//     'message' => 'Search term is valid.'
// ]
```

#### `isSearchTermValid(string $term): bool`
Checks if a search term is valid (legacy method).

> **Deprecated**: Use `validateSearchTerm()` for detailed validation messages.

```php
if (DynamicFilter::isSearchTermValid('valid term')) {
    // Process search
}
```

### Error Handling Methods

#### `getLastError(): ?array`
Gets the last error that occurred during filter operations.

```php
$error = DynamicFilter::getLastError();
if ($error) {
    // Handle error
}
```

#### `clearLastError(): void`
Clears the last recorded error.

```php
DynamicFilter::clearLastError();
```

### Configuration Methods

#### `getOperators(): array`
Gets all available operators.

```php
$operators = DynamicFilter::getOperators();
```

#### `getOperator(string $key): string`
Gets a specific operator by key.

```php
$operator = DynamicFilter::getOperator('gt'); // Returns '>'
```

#### `hasOperator(string $key): bool`
Checks if an operator exists.

```php
if (DynamicFilter::hasOperator('in')) {
    // Operator exists
}
```

#### `getGlobalWhitelist(): array`
Gets the global whitelist of filterable fields.

```php
$whitelist = DynamicFilter::getGlobalWhitelist();
```

#### `getPaginationConfig(): array`
Gets the pagination configuration.

```php
$pagination = DynamicFilter::getPaginationConfig();
```

#### `getCustomFilters(): array`
Gets the custom filter classes.

```php
$filters = DynamicFilter::getCustomFilters();
```

#### `getFilterPresets(): array`
Gets the filter presets.

```php
$presets = DynamicFilter::getFilterPresets();
```

#### `getConfig(string $key, mixed $default = null): mixed`
## Filter Types

### Basic Filtering

Basic filtering allows you to filter records by exact matches on model attributes.

```php
// URL: /api/posts?status=published&category_id=5

$posts = Post::filter([
    'status' => 'published',
    'category_id' => 5,
])->get();
```

### Using Operators

You can use various operators for more complex filtering:

```php
$posts = Post::filter([
    'views' => ['gt' => 100],                // Greater than 100
    'created_at' => [
        'gte' => '2023-01-01',               // On or after Jan 1, 2023
        'lte' => '2023-12-31'                // On or before Dec 31, 2023
    ],
    'title' => [
        'like' => '%Laravel%'                // Title contains 'Laravel'
    ],
    'status' => ['in' => ['published', 'draft']], // Status is either published or draft
    'deleted_at' => ['null' => true]         // Only non-deleted records
])->get();
```

### Search

Full-text search across multiple fields:

```php
// Search in all searchable fields
$posts = Post::search('Laravel')->get();

// Search in specific fields
$posts = Post::search('Laravel', ['title', 'content'])->get();

// Search with AND condition (all terms must match)
$posts = Post::search('Laravel 9', [], 'and')->get();
```

### Sorting

Sort results by one or more fields using a simple, intuitive syntax:

```php
// Basic sorting
$posts = Post::sort('title,asc')->get();

// Alternative format with prefix
$posts = Post::sort('-created_at')->get(); // Same as 'created_at,desc'

// Multiple sort fields
$posts = Post::sort('views,desc,title,asc')->get();

// Using query parameters with default
$sort = $request->input('sort', 'created_at,desc');
$posts = Post::sort($sort)->get();

// Multiple sort parameters in URL
// GET /posts?sort=views,desc&sort=title,asc
$posts = Post::sort($request->sort)->get();
```

## Custom Filters

### Creating Custom Filters

Create a custom filter class:

```php
namespace App\Filters;

use Dibakar\LaravelDynamicFilters\Services\BaseFilter;
use Illuminate\Database\Eloquent\Builder;

class PublishedLastDaysFilter extends BaseFilter
{
    public function apply(Builder $query, $value): Builder
    {
        return $query->where('published_at', '>=', now()->subDays($value));
    }
}
```

### Registering Custom Filters

Register your custom filters in the configuration:

```php
// config/dynamic-filters.php

'custom_filters' => [
    'published_last_days' => \App\Filters\PublishedLastDaysFilter::class,
],
```

Use the custom filter:

```php
// URL: /api/posts?published_last_days=7

$posts = Post::filter([
    'published_last_days' => 7, // Posts published in the last 7 days
])->get();
```

## Advanced Usage

### Relationship Filtering

Filter based on related models:

```php
// URL: /api/posts?author.name=John&category.slug=laravel

$posts = Post::filter([
    'author.name' => 'John',
    'category.slug' => 'laravel',
])->get();
```

### Filter Presets

Define reusable filter presets in your model:

```php
class Post extends Model
{
    use HasDynamicFilter;
    
    protected $filterPresets = [
        'popular' => [
            'views' => ['gt' => 1000],
            'is_featured' => true,
        ],
        'recent' => [
            'created_at' => ['gte' => now()->subWeek()],
        ],
    ];
}

// Usage
$popularPosts = Post::filter('popular')->get();
```

### Pagination

```php
// With default pagination
$posts = Post::filter($request->all())->paginate(15);

// With custom page parameter
$posts = Post::filter($request->all())
    ->paginate(
        $request->input('per_page', 15),
        ['*'],
        'page',
        $request->input('page', 1)
    );
```

### Security

#### Whitelisting Filterable Fields

```php
class Post extends Model
{
    use HasDynamicFilter;
    
    // Only these fields can be filtered
    protected $filterable = [
        'title',
        'status',
        'created_at',
        'category_id',
    ];
    
    // Only these fields can be searched
    protected $searchable = [
        'title',
        'content',
        'author.name',
    ];
    
    // Only these fields can be used for sorting
    protected $sortable = [
        'created_at',
        'title',
        'views',
    ];
}
```

## Error Handling

The package throws specific exceptions that you can catch and handle:

```php
use Dibakar\LaravelDynamicFilters\Exceptions\FilterException;

try {
    $posts = Post::filter($request->all())->get();
} catch (FilterException $e) {
    return response()->json([
        'error' => $e->getMessage(),
        'context' => $e->getContext(),
    ], 400);
}
```

## Performance Tips

1. **Index Your Columns**: Ensure columns used in filtering and sorting are indexed.

2. **Use `select()` Wisely**: Only select the columns you need.
   ```php
   $posts = Post::filter($filters)
       ->select('id', 'title', 'created_at')
       ->with('author:id,name')
       ->get();
   ```

3. **Eager Load Relationships**: Use `with()` to avoid N+1 query problems.
   ```php
   $posts = Post::filter($filters)
       ->with(['author', 'category'])
       ->get();
   ```

4. **Limit Result Size**: Always use pagination or limit for large datasets.
   ```php
   $posts = Post::filter($filters)->paginate(15);
   ```

#### `setConfig(string $key, mixed $value): void`
Sets a configuration value.

```php
DynamicFilter::setConfig('search.min_term_length', 3);
```

## Filter Types

### Basic Filtering

```php
// GET /posts?status=published
$posts = Post::filter(request()->query())->get();
```

### Operators

| Operator   | Description                      | Example                      | Error Handling                                                                 |
|------------|----------------------------------|------------------------------|-------------------------------------------------------------------------------|
| `=`        | Equals                           | `?status=active`             | Validates value type matches database column type                             |
| `!=`       | Not equals                       | `?status[neq]=archived`      | Validates value type matches database column type                             |
| `>`        | Greater than                     | `?price[gt]=100`             | Validates numeric value                                                       |
| `>=`       | Greater than or equal            | `?age[gte]=18`               | Validates numeric value                                                       |
| `<`        | Less than                        | `?price[lt]=100`             | Validates numeric value                                                       |
| `<=`       | Less than or equal               | `?age[lte]=30`               | Validates numeric value                                                       |
| `like`     | Case-sensitive pattern matching   | `?name[like]=%john%`         | Validates string value, escapes special characters                           |
| `ilike`    | Case-insensitive pattern matching | `?email[ilike]=%gmail.com`   | Validates string value, escapes special characters                           |
| `in`       | Value in array                    | `?status[in]=active,pending` | Validates array values, checks against allowed values if defined             |
| `between`  | Value between range               | `?age[between]=18,30`        | Validates exactly 2 values, checks min <= max                                |
| `null`     | Field is NULL                     | `?deleted_at[null]`          | No value needed                                                              |
| `not_null` | Field is not NULL                 | `?published_at[not_null]`    | No value needed                                                              |

### Search

```php
// Search in title and content
// GET /posts?search=laravel
$posts = Post::search(request('search'))->get();
```

### Sorting

Sorting allows you to order your query results by one or more fields in ascending or descending order. The package supports multiple formats for maximum flexibility.

#### Basic Usage

```php
// Basic ascending sort
$posts = Post::sort('title')->get();
// or explicitly
$posts = Post::sort('title,asc')->get();

// Descending sort (two equivalent ways)
$posts = Post::sort('created_at,desc')->get();
$posts = Post::sort('-created_at')->get();

// Multiple sort criteria
$posts = Post::sort('category,asc,created_at,desc')->get();
// or as an array
$posts = Post::sort([
    'category' => 'asc',
    'created_at' => 'desc'
])->get();
```

#### Sorting with Relationships

Sort by related model fields using dot notation:

```php
// Sort by author's name and then by post date
$posts = Post::with('author')
    ->sort('author.name,asc,-created_at')
    ->get();
```

#### Using in Controllers

```php
public function index(Request $request)
{
    $validated = $request->validate([
        'sort' => 'sometimes|string',
        'per_page' => 'sometimes|integer|min:1|max:100'
    ]);

    return Post::with('author')
        ->filter($request->except(['sort', 'page', 'per_page']))
        ->sort($validated['sort'] ?? 'created_at,desc')
        ->paginate($validated['per_page'] ?? 15);
}
```

#### Configuring Sortable Fields

For security, you can define which fields are sortable in your model:

```php
class Post extends Model
{
    use HasDynamicFilter;
    
    protected $sortable = [
        'title',
        'created_at',
        'views',
        'author.name', // Sort by relationship
    ];
    
    // Optional: Set default sort
    protected $defaultSort = 'created_at,desc';
}
```

#### Error Handling

Handle invalid sort fields gracefully:

```php
try {
    $posts = Post::sort($request->sort)->get();
} catch (\Dibakar\LaravelDynamicFilters\Exceptions\FilterException $e) {
    return response()->json([
        'error' => 'Invalid sort parameter',
        'details' => $e->getMessage()
    ], 400);
}
```

#### Available Sort Methods

##### `sort(string|array $sortParams, array $allowedColumns = []): Builder`
Applies sorting to the query.

```php
// Basic usage
Post::sort('title,asc');

// With allowed columns for security
Post::sort($request->sort, ['title', 'created_at', 'author.name']);
```

##### `getSortConfig(): array`
Get current sort configuration:

```php
$config = DynamicFilter::getSortConfig();
/* Returns:
[
    'default_direction' => 'asc',
    'parameter_name' => 'sort',
    'ignore_case' => true,
]
*/
```

##### `setSortConfig(array $config): void`
Update sort configuration:

```php
DynamicFilter::setSortConfig([
    'default_direction' => 'desc',
    'parameter_name' => 'order_by',
    'ignore_case' => false,
]);
```

## Custom Filters

### Creating Custom Filters

Create a custom filter class:

```php
namespace App\Filters;

use Dibakar\LaravelDynamicFilters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class PublishedInLastDaysFilter implements FilterContract
{
    public function apply(Builder $query, $value, string $property): Builder
    {
        $days = is_numeric($value) ? (int) $value : 7;
        return $query->where('published_at', '>=', now()->subDays($days));
    }
    
    public function validate($value): bool
    {
        return is_numeric($value) && $value > 0;
    }
}
```

### Registering Custom Filters

Register your custom filter in `config/dynamic-filters.php`:

```php
'custom_filters' => [
    'published_in_days' => \App\Filters\PublishedInLastDaysFilter::class,
],
```

Use it in your API:

```
GET /posts?published_in_days=30
```

## Advanced Usage

### Relationship Filtering

```php
// Filter posts by author name (assuming Post belongsTo User)
// GET /posts?user.name=John

// Filter by nested relationships
// GET /posts?user.profile.country=USA
```

### Filter Presets

Define presets in `config/dynamic-filters.php`:

```php
'presets' => [
    'recent' => [
        'created_at' => ['gt' => now()->subMonth()->toDateString()],
        'sort' => '-created_at',
    ],
    'popular' => [
        'views' => ['gt' => 1000],
        'status' => 'published',
        'sort' => '-views',
    ],
],
```

Use presets in your API:

```
GET /posts?preset=recent
```

### Pagination

```php
// With pagination
$posts = Post::filter(request()->query())->paginate(15);

// With custom page parameter
$posts = Post::filter(request()->query())->paginate(
    perPage: request('per_page', 15),
    page: request('page', 1)
);
```

## Example Use Cases

### 1. E-commerce Product Filtering

```php
// Get featured products in specific categories
$products = Product::filter([
    'featured' => true,
    'category_id' => ['in' => [1, 5, 9]],
    'price' => [
        'between' => [
            'min' => 10,
            'max' => 100
        ]
    ],
    'rating' => ['gte' => 4],
    'in_stock' => true,
    'sort' => '-popularity,price'
])->paginate(12);
```

### 2. Blog Post Management

```php
// Get published posts with specific tags, sorted by publish date
$posts = Post::filter([
    'status' => 'published',
    'published_at' => ['lte' => now()],
    'tags' => ['in' => ['laravel', 'php']],
    'author.role' => 'admin',  // Filter by relationship
    'sort' => '-published_at',
    'per_page' => 15,
    'page' => request('page', 1)
])->with(['author', 'category']);
```

### 3. User Management System

```php
// Search and filter users with role-based access
$users = User::filter([
    'search' => request('query'),  // Full-text search
    'status' => 'active',
    'role' => ['in' => ['editor', 'author']],
    'last_login' => ['gt' => now()->subMonths(3)],
    'sort' => 'name',
    'per_page' => 25
]);
```

### 4. Analytics Dashboard

```php
// Get filtered analytics data
$metrics = Analytics::filter([
    'date_range' => [
        'start' => now()->subMonth(),
        'end' => now()
    ],
    'metrics' => ['page_views', 'unique_visitors'],
    'group_by' => ['date', 'country'],
    'filters' => [
        'device_type' => 'mobile',
        'traffic_source' => ['in' => ['organic', 'direct']]
    ]
]);
```

### 5. Complex Nested Filtering

```php
// Advanced filtering with nested conditions
$results = Model::filter([
    '_group' => [
        'boolean' => 'and',
        'filters' => [
            'status' => 'active',
            'category' => ['in' => [1, 2, 3]]
        ],
        'nested' => [
            [
                'boolean' => 'or',
                'filters' => [
                    'priority' => 'high',
                    'is_featured' => true
                ]
            ]
        ]
    ]
]);
```

## Performance Optimization Examples

### 1. Eager Loading Relationships

```php
// Always eager load relationships to prevent N+1 queries
$posts = Post::with(['author', 'tags', 'category'])
    ->filter(request()->query())
    ->paginate(15);
```

### 2. Select Only Required Fields

```php
// Select only the fields you need
$users = User::select(['id', 'name', 'email', 'role'])
    ->filter($filters)
    ->get();
```

### 3. Use Indexed Columns for Filtering

```php
// Ensure your database has indexes on frequently filtered columns
Schema::table('posts', function (Blueprint $table) {
    $table->index('status');
    $table->index('published_at');
    $table->index(['category_id', 'published_at']); // Composite index
});
```

### 4. Cache Frequently Accessed Queries

```php
// Cache filter results for better performance
$posts = Cache::remember('filtered_posts_' . md5(json_encode($filters)), 
    now()->addHour(), 
    function () use ($filters) {
        return Post::filter($filters)->get();
    }
);
```

## Common Filter Patterns

### Date/Time Filtering

```php
// Today's records
$today = Model::filter(['created_at' => now()->toDateString()]);

// Last 7 days
$recent = Model::filter([
    'created_at' => ['gt' => now()->subDays(7)]
]);

// Specific date range
$range = Model::filter([
    'created_at' => [
        'between' => [
            'start' => '2023-01-01',
            'end' => '2023-12-31'
        ]
    ]
]);
```

### Text Search with Multiple Fields

```php
// Search across multiple fields with different weights
$results = Model::search('search term', [
    'title' => 10,    // Higher weight
    'content' => 5,   // Medium weight
    'tags' => 3,      // Lower weight
]);
```

### Dynamic Filter Building

```php
// Build filters dynamically based on request
$filters = [];

if ($request->has('category')) {
    $filters['category_id'] = $request->category;
}

if ($request->has('min_price')) {
    $filters['price'] = ['gte' => $request->min_price];
}

$products = Product::filter($filters)->get();
```

## Security Best Practices

### 1. Field Whitelisting

```php
// In your model
protected $filterable = [
    'id', 'title', 'status', 'created_at',  // Explicitly list allowed fields
    'author_id' => 'author.id'  // Relationship filtering with explicit mapping
];

// In config/dynamic-filters.php
'global_whitelist' => [
    'id', 'status', 'created_at'  // Application-wide allowed fields
],
```

### 2. Input Validation

```php
// Validate filter input in your controller
$validated = $request->validate([
    'filters' => 'sometimes|array',
    'filters.status' => 'sometimes|in:published,draft,archived',
    'filters.created_at' => 'sometimes|date',
    'sort' => 'sometimes|string|regex:/^-?[a-zA-Z0-9_,]+$/',
    'per_page' => 'sometimes|integer|min:1|max:100',
]);

$posts = Post::filter($validated['filters'] ?? []);
```

### 3. Role-Based Filtering

```php
// In a custom filter class
class RoleBasedFilter implements FilterContract
{
    public function apply(Builder $query, $value, string $property): Builder
    {
        if (!auth()->user()->isAdmin()) {
            // Only allow filtering by certain fields for non-admins
            if (!in_array($property, ['status', 'created_at'])) {
                return $query;
            }
        }
        
        // Apply the filter
        return $query->where($property, $value);
    }
}
```

### 4. Rate Limiting

```php
// In your API routes or controller
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/api/posts', 'PostController@index');
});
```

## Performance Optimization

### 1. Database Indexing

```php
// Add indexes for frequently filtered columns
Schema::table('posts', function (Blueprint $table) {
    $table->index('status');
    $table->index('created_at');
    $table->index(['category_id', 'published_at']);
});
```

### 2. Query Optimization

```php
// Use select() to limit fetched columns
$posts = Post::select(['id', 'title', 'slug', 'created_at'])
    ->filter($filters)
    ->with(['author:id,name', 'category:id,name'])  // Only load necessary relationships
    ->paginate(15);

// Use cursor() for large datasets
foreach (Post::filter($filters)->cursor() as $post) {
    // Process each post without loading all into memory
}
```

### 3. Caching Strategies

```php
// Cache filter results
$cacheKey = 'filtered_posts_' . md5(json_encode($filters));

$posts = Cache::remember($cacheKey, now()->addHour(), function () use ($filters) {
    return Post::filter($filters)
        ->with(['author', 'category'])
        ->get();
});

// Cache individual filter presets
$presets = [
    'recent' => [
        'created_at' => ['gt' => now()->subMonth()->toDateString()],
        'sort' => '-created_at',
    ],
    // ... other presets
];
```
### 4. Monitoring and Logging

```php
// Log slow queries
DB::listen(function ($query) {
    if ($query->time > 100) {  // Log queries slower than 100ms
        Log::warning('Slow query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time
        ]);
    }
});
```

1. Add database indexes on frequently filtered columns
2. Use `$searchable` to limit which fields are searchable
3. Consider using database full-text search for large datasets
4. Use `select()` to only fetch required columns
5. Implement caching for expensive filter operations

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
