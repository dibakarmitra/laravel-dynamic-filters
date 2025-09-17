<?php

namespace Dibakar\LaravelDynamicFilters;

use Illuminate\Support\ServiceProvider;
use Dibakar\LaravelDynamicFilters\Services\FilterParser;
use Dibakar\LaravelDynamicFilters\Services\SearchHandler;
use Dibakar\LaravelDynamicFilters\Services\RelationshipHandler;
use Dibakar\LaravelDynamicFilters\Services\FilterManager;

class DynamicFiltersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/dynamic-filters.php' => config_path('dynamic-filters.php'),
        ], 'dynamic-filters-config');

        $this->publishes([
            __DIR__.'/../config/dynamic-filters.php' => config_path('dynamic-filters.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/dynamic-filters.php', 'dynamic-filters'
        );

        $this->registerServices();
    }

    protected function registerServices(): void
    {
        $this->app->singleton('dynamic-filters.parser', function ($app) {
            return new FilterParser(
                $app->make('db.connection'),
                config('dynamic-filters', [])
            );
        });

        $this->app->singleton('dynamic-filters.search', function ($app) {
            return new SearchHandler(
                config('dynamic-filters.search', [])
            );
        });

        $this->app->singleton('dynamic-filters.relationships', function ($app) {
            return new RelationshipHandler(
                $app->make('db.connection')
            );
        });

        $this->app->singleton('dynamic-filters', function ($app) {
            return new FilterManager(
                $app->make('dynamic-filters.parser'),
                $app->make('dynamic-filters.search'),
                $app->make('dynamic-filters.relationships'),
                config('dynamic-filters', [])
            );
        });
    }

    public function provides(): array
    {
        return [
            'dynamic-filters',
            'dynamic-filters.parser',
            'dynamic-filters.search',
            'dynamic-filters.relationships',
        ];
    }
}
