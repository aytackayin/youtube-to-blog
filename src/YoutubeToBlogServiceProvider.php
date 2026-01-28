<?php

namespace Aytackayin\YoutubeToBlog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class YoutubeToBlogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/youtube-to-blog.php',
            'youtube-to-blog'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/youtube-to-blog.php' => config_path('youtube-to-blog.php'),
        ], 'youtube-to-blog-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'youtube-to-blog-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register routes
        $this->registerRoutes();

        // Publish Chrome extension files
        $this->publishes([
            __DIR__ . '/../extensions/youtube-to-blog/' => base_path('extensions/youtube-to-blog'),
        ], 'youtube-to-blog-extension');
    }

    /**
     * Register API routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => 'api/youtube',
            'middleware' => [\Aytackayin\YoutubeToBlog\Http\Middleware\YouTubeExtensionAuth::class],
        ], function () {
            Route::get('/categories', [\Aytackayin\YoutubeToBlog\Http\Controllers\YouTubeIntegrationController::class, 'getCategories']);
            Route::post('/categories', [\Aytackayin\YoutubeToBlog\Http\Controllers\YouTubeIntegrationController::class, 'storeCategory']);
            Route::post('/store', [\Aytackayin\YoutubeToBlog\Http\Controllers\YouTubeIntegrationController::class, 'store']);
            Route::get('/status/{id}', [\Aytackayin\YoutubeToBlog\Http\Controllers\YouTubeIntegrationController::class, 'checkStatus']);
        });
    }
}
