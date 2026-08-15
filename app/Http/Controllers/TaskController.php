<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

final class TaskController extends Controller
{
    public function index(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function store(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function show(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function update(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function destroy(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }
}
