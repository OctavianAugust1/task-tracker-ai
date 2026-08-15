<?php

declare(strict_types=1);

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
    Route::patch('/tasks/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->whereNumber('id');
});
