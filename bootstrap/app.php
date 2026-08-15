<?php

use App\Exceptions\CorruptTaskStorage;
use App\Exceptions\TaskStorageException;
use App\Http\Middleware\RejectMalformedJson;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable as BaseThrowable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RejectMalformedJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*'),
        );

        $exceptions->render(function (CorruptTaskStorage $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'storage_corrupted',
                message: 'Task storage is corrupted',
                details: [],
                status: 500,
            );
        });

        $exceptions->render(function (TaskStorageException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'storage_error',
                message: 'Task storage is unavailable',
                details: [],
                status: 500,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'not_found',
                message: 'Resource not found',
                details: [],
                status: 404,
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'method_not_allowed',
                message: 'Method not allowed',
                details: [],
                status: 405,
            );
        });

        $exceptions->render(function (BaseThrowable $exception, Request $request) {
            if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'internal_error',
                message: 'Internal server error',
                details: [],
                status: 500,
            );
        });

    })->create();
