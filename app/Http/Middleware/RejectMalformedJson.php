<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

final class RejectMalformedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();

        if ($request->isJson() && trim($content) !== '') {
            try {
                json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return ApiErrorResponse::make(
                    code: 'malformed_json',
                    message: 'Malformed JSON request body',
                    details: [],
                    status: 400,
                );
            }
        }

        return $next($request);
    }
}
