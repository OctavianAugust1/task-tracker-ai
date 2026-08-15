<?php

namespace App\Providers;

use App\Contracts\TaskRepository;
use App\Repositories\JsonTaskRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TaskRepository::class, function (Application $app): JsonTaskRepository {
            return new JsonTaskRepository((string) $app['config']->get('tasks.file'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
