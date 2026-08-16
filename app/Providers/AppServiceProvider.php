<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CategoryRepository;
use App\Contracts\TaskRepository;
use App\Repositories\JsonTaskRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JsonTaskRepository::class, function (Application $app): JsonTaskRepository {
            return new JsonTaskRepository($app->make(ConfigRepository::class)->string('tasks.file'));
        });
        $this->app->bind(TaskRepository::class, JsonTaskRepository::class);
        $this->app->bind(CategoryRepository::class, JsonTaskRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
