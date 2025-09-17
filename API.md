# Laravel Dynamic Filters - API Documentation

## Table of Contents
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Facade Methods](#facade-methods)
  - [Filtering](#filtering-methods)
  - [Search](#search-methods)
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
- [Example Filters](#example-filters)
  - [Date Range Filter](#date-range-filter)
  - [Tag Filter](#tag-filter)
  - [Full-Text Search](#full-text-search)
  - [Published in Last X Days](#published-in-last-x-days)

## Installation

```bash
composer require dibakar/laravel-dynamic-filters
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Dibakar\\LaravelDynamicFilters\\DynamicFiltersServiceProvider" --tag="config"
```

## Basic Usage

Add the `HasDynamicFilter` trait to your model:

```php
use Dibakar\LaravelDynamicFilters\Traits\HasDynamicFilter;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasDynamicFilter;
    
    protected $filterable = [
        'title',
        'status',
        'created_at',
        'category_id',
    ];
    
    protected $searchable = [
        'title',
        'content',
    ];
}
```

## Facade Methods

### Filtering Methods

#### `apply(Builder $query, array $filters = []): Builder`
Applies filters to an Eloquent query.

```php
use Dibakar\LaravelDynamicFilters\Facades\DynamicFilter;

$filtered = DynamicFilter::apply(Post::query(), [
    'status' => 'published',
    'views' => ['gt' => 100]
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
$results = DynamicFilter::search(Post::query(), 'search term', ['title', 'content'])->get();
```

#### `getSearchConfig(): array`
Gets the current search configuration.

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

#### `isSearchTermValid(string $term): bool`
Checks if a search term is valid.

```php
if (DynamicFilter::isSearchTermValid('valid term')) {
    // Process search
}
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
Gets a configuration value.

```php
$value = DynamicFilter::getConfig('search.min_term_length', 2);
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

| Operator | Description           | Example                      |
|----------|-----------------------|------------------------------|
| =        | Equals                | `?status=active`             |
| !=       | Not equals            | `?status[neq]=inactive`      |
| >        | Greater than          | `?views[gt]=100`             |
| >=       | Greater than or equal | `?rating[gte]=4`             |
| <        | Less than             | `?price[lt]=100`             |
| <=       | Less than or equal    | `?age[lte]=30`               |
| like     | Like (case-sensitive) | `?name[like]=%john%`         |
| ilike    | Like (case-insensitive)| `?email[ilike]=%gmail.com`   |
| in       | In array              | `?status[in]=active,pending` |
| not_in   | Not in array          | `?id[not_in]=1,2,3`          |
| between  | Between values        | `?created_at[between]=2023-01-01,2023-12-31` |
| null     | Is null               | `?deleted_at[null]`          |
| notnull  | Is not null           | `?updated_at[notnull]`       |

### Search

```php
// Search in title and content
// GET /posts?search=laravel
$posts = Post::search(request('search'))->get();
```

### Sorting

```php
// Sort by created_at in descending order
// GET /posts?sort=-created_at

// Multiple sort fields
// GET /posts?sort=-created_at,title
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

## Testing Your Filters

### 1. Unit Testing Filter Classes

```php
/** @test */
public function it_filters_posts_by_status()
{
    $published = Post::factory()->create(['status' => 'published']);
    $draft = Post::factory()->create(['status' => 'draft']);

    $results = Post::filter(['status' => 'published'])->get();

    $this->assertCount(1, $results);
    $this->assertTrue($results->contains('id', $published->id));
    $this->assertFalse($results->contains('id', $draft->id));
}
```

### 2. Feature Testing API Endpoints

```php
/** @test */
public function it_filters_posts_via_api()
{
    $post = Post::factory()->create(['status' => 'published']);
    Post::factory()->create(['status' => 'draft']);

    $response = $this->getJson('/api/posts?status=published');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['id' => $post->id]);
}
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
