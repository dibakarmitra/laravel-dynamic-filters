# Laravel Dynamic Filters

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dibakar/laravel-dynamic-filters.svg?style=flat-square)](https://packagist.org/packages/dibakar/laravel-dynamic-filters)
[![Total Downloads](https://img.shields.io/packagist/dt/dibakar/laravel-dynamic-filters.svg?style=flat-square)](https://packagist.org/packages/dibakar/laravel-dynamic-filters)
[![License](https://img.shields.io/github/license/dibakar/laravel-dynamic-filters?style=flat-square)](https://github.com/dibakar/laravel-dynamic-filters)
[![PHP Version](https://img.shields.io/packagist/php-v/dibakar/laravel-dynamic-filters?style=flat-square)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x+-orange?style=flat-square)](https://laravel.com/)
[![Tests](https://github.com/dibakar/laravel-dynamic-filters/actions/workflows/tests.yml/badge.svg)](https://github.com/dibakar/laravel-dynamic-filters/actions/workflows/tests.yml)

A flexible and powerful filtering system for Laravel Eloquent models. This package allows you to easily add dynamic filtering, searching, and sorting to your Laravel applications with minimal configuration.

## ✨ Features

- **Intuitive API**: Simple and expressive syntax for complex filtering needs
- **Dynamic Filtering**: Filter models using query parameters with support for complex conditions
- **Advanced Search**: Full-text search with term normalization and blacklisting
- **Relationship Support**: Filter across model relationships with nested conditions
- **Type Safety**: Strict type checking and value casting with proper validation
- **Performance Optimized**: Efficient query building with minimal overhead
- **Extensible**: Easy to extend with custom filters and search logic
- **Comprehensive Testing**: Thoroughly tested with PHPUnit
- **Modern PHP**: Built with PHP 8.1+ features and type hints
- **Laravel Integration**: Seamless integration with Laravel's Eloquent ORM

## 🚀 Installation

### Requirements
- PHP 8.2 or higher
- Laravel 10.x or later

### Install via Composer

```bash
composer require dibakar/laravel-dynamic-filters
```

### Configuration

Publish the configuration file (optional but recommended):

```bash
php artisan vendor:publish --tag=dynamic-filters-config
```

This will create a `dynamic-filters.php` file in your `config` directory where you can customize the package behavior.

### Service Provider

For Laravel versions that don't support package auto-discovery, register the service provider in `config/app.php`:

```php
'providers' => [
    // Other service providers...
    Dibakar\LaravelDynamicFilters\DynamicFiltersServiceProvider::class,
],

'aliases' => [
    // Other aliases...
    'DynamicFilter' => Dibakar\LaravelDynamicFilters\Facades\DynamicFilter::class,
],
```

## 📦 Version Compatibility

| Laravel | PHP     | Package |
|---------|---------|---------|
| 12.x    | 8.2+    | ^1.0    |
| 11.x    | 8.2+    | ^1.0    |
| 10.x    | 8.1+    | ^1.0    |

## 🛠 Basic Usage

### 1. Setting Up Your Model

Add the `HasDynamicFilter` trait to your Eloquent model and define the filterable and searchable fields:

```php
use Dibakar\LaravelDynamicFilters\Traits\HasDynamicFilter;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasDynamicFilter;
    
    /**
     * The attributes that are searchable.
     *
     * @var array
     */
    protected $searchable = [
        'title', 
        'content', 
        'author.name',  // Search in relationships
        'tags.name'     // Search in many-to-many relationships
    ];
    
    /**
     * The attributes that are filterable.
     *
     * @var array
     */
    protected $filterable = [
        'status',                   // Simple filter: ?status=published
        'category_id',              // Exact match: ?category_id=5
        'created_at' => [           // Date filtering
            'operators' => ['=', '>', '<', '>=', '<=', '!='],
            'cast' => 'date',
        ],
        'views' => [                // Numeric filtering
            'operators' => ['=', '>', '<', '>=', '<=', '!='],
            'cast' => 'int',
        ],
        'author_id' => 'author.id', // Relationship filtering
        'tag_id' => 'tags.id'      // Many-to-many relationship
    ];
    
    /**
     * Get the default filters that should be applied to all queries.
     *
     * @return array
     */
    public function getDefaultFilters()
    {
        return [
            'status' => 'published',
            'sort' => '-created_at',
        ];
    }
}
```

### 2. Basic Filtering

Filter your models using query parameters in your controller:

```php
// GET /posts?status=published&created_at[gt]=2023-01-01&sort=-views,title
public function index(Request $request)
{
    $posts = Post::filter($request->query())
        ->with(['author', 'category', 'tags']) // Eager load relationships
        ->paginate($request->per_page ?? 15);

    return response()->json($posts);
}
```

### 3. Search Functionality

Search across searchable fields with a simple API:

```php
// GET /posts?q=laravel+framework
public function search(Request $request)
{
    $posts = Post::search($request->q)
        ->filter($request->except('q')) // Apply additional filters
        ->paginate($request->per_page ?? 15);

    return response()->json($posts);
}
```

### 4. Sorting Results

Sort your results using the `sort` parameter:

```
# Sort by most recent first, then by title (ascending)
GET /posts?sort=-created_at,title

# Sort by views (descending)
GET /posts?sort=-views
```

### 5. Pagination

Pagination works seamlessly with Laravel's built-in pagination:

```php
// GET /posts?page=2&per_page=20
$posts = Post::filter($request->query())
    ->paginate($request->per_page ?? 15);
```

## 🚀 Advanced Usage

### 1. Complex Filter Groups

Create complex filter conditions with AND/OR logic:

```php
// Example: (status = 'published' AND (title LIKE '%Laravel%' OR views > 100)) AND (author_id = 1 OR author_id = 2)
$filters = [
    '_group' => [
        'boolean' => 'and',
        'filters' => [
            'status' => 'published',
        ],
        'nested' => [
            [
                'boolean' => 'or',
                'filters' => [
                    'title' => ['like' => '%Laravel%'],
                    'views' => ['gt' => 100],
                ],
            ],
            [
                'boolean' => 'or',
                'filters' => [
                    'author_id' => [1, 2],
                ],
            ],
        ],
    ],
];

$posts = Post::filter($filters)->get();
```

### 2. Custom Filter Classes

For complex filtering logic, create a custom filter class:

```php
<?php

namespace App\Filters;

use Dibakar\LaravelDynamicFilters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PublishedInLastDaysFilter implements FilterContract
{
    /**
     * Apply the filter to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $value
     * @param string $property
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $query, $value, string $property): Builder
    {
        $days = is_numeric($value) ? (int) $value : 7; // Default to 7 days if invalid
        
        return $query->where('published_at', '>=', Carbon::now()->subDays($days));
    }
    
    /**
     * Validate the filter value.
     *
     * @param mixed $value
     * @return bool
     */
    public function validate($value): bool
    {
        return is_numeric($value) && $value > 0;
    }
    
    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function getValidationMessage(): string
    {
        return 'The days parameter must be a positive number.';
    }
}
```

Register your custom filter in `config/dynamic-filters.php`:

```php
'custom_filters' => [
    'published_in_days' => \App\Filters\PublishedInLastDaysFilter::class,
    'active_users' => \App\Filters\ActiveUsersFilter::class,
    // Add more custom filters as needed
],
```

Now use it in your API:

```php
// GET /posts?published_in_days=30
$recentPosts = Post::filter(request()->query())->get();
```

### 3. Filter Presets

Define common filter presets in your configuration:

```php
// config/dynamic-filters.php
'presets' => [
    'recent' => [
        'created_at' => ['gt' => now()->subMonth()->toDateString()],
        'sort' => '-created_at',
    ],
    'popular' => [
        'views' => ['gt' => 1000],
        'status' => 'published',
        'sort' => '-views,title',
    ],
    'featured' => [
        'is_featured' => true,
        'status' => 'published',
        'sort' => '-created_at',
    ],
],
```

Use presets in your API:

```
# Get recent posts
GET /posts?preset=recent

# Get popular posts
GET /posts?preset=popular

# Combine with additional filters
GET /posts?preset=featured&category=laravel
```

### 4. API Resource Integration

Easily integrate with Laravel's API Resources:

```php
// app/Http/Resources/PostResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when($this->showFullContent, $this->content),
            'status' => $this->status,
            'views' => $this->views,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => UserResource::make($this->whenLoaded('author')),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}

// In your controller
public function index(Request $request)
{
    $posts = Post::filter($request->query())
        ->with(['author', 'category', 'tags'])
        ->paginate($request->per_page ?? 15);

    return PostResource::collection($posts);
}
```

### 5. Performance Optimization

#### Database Indexing

```php
// In a migration
public function up()
{
    Schema::table('posts', function (Blueprint $table) {
        // Single column indexes
        $table->index('status');
        $table->index('published_at');
        $table->index('views');
        
        // Composite index for common filter combinations
        $table->index(['status', 'published_at']);
        $table->index(['category_id', 'status', 'published_at']);
    });
}
```

#### Selective Field Loading

```php
// Only select the fields you need
$posts = Post::select([
        'id', 
        'title', 
        'slug', 
        'excerpt', 
        'status', 
        'published_at',
        'author_id',
        'category_id'
    ])
    ->with([
        'author:id,name,avatar',
        'category:id,name,slug',
        'tags:id,name,slug'
    ])
    ->filter($filters)
    ->paginate(15);
```

### 6. Error Handling

Handle filter validation errors gracefully:

```php
try {
    $posts = Post::filter($request->query())->paginate(15);
    return PostResource::collection($posts);
} catch (\Dibakar\LaravelDynamicFilters\Exceptions\InvalidFilterException $e) {
    return response()->json([
        'message' => 'Invalid filter parameters',
        'errors' => $e->getMessage()
    ], 400);
} catch (\Exception $e) {
    return response()->json([
        'message' => 'An error occurred while processing your request.',
        'error' => config('app.debug') ? $e->getMessage() : 'Server error'
    ], 500);
}
```

### 7. Testing Your Filters

Write tests to ensure your filters work as expected:

```php
// tests/Feature/PostFilterTest.php

public function test_can_filter_posts_by_status()
{
    $published = Post::factory()->create(['status' => 'published']);
    $draft = Post::factory()->create(['status' => 'draft']);

    $response = $this->getJson('/api/posts?status=published');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['id' => $published->id])
        ->assertJsonMissing(['id' => $draft->id]);
}

public function test_can_search_posts()
{
    $laravelPost = Post::factory()->create([
        'title' => 'Getting Started with Laravel',
        'content' => 'Laravel is a web application framework...'
    ]);
    
    $symfonyPost = Post::factory()->create([
        'title' => 'Symfony vs Laravel',
        'content' => 'Comparison between the two frameworks...'
    ]);

    $response = $this->getJson('/api/posts?q=laravel');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $laravelPost->id])
        ->assertJsonFragment(['id' => $symfonyPost->id]);
}
```

## Available Operators

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

## Security

By default, only fields defined in the `$filterable` array can be filtered. This is a security measure to prevent unauthorized filtering on sensitive fields.

You can also define a global whitelist in the config file that applies to all models:

```php
'global_whitelist' => [
    'id',
    'status',
    'created_at',
    'updated_at',
],
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email dibakarmitra07@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
