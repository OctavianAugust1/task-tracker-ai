<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Symfony\Component\HttpFoundation\Response;

final class TaskController extends Controller
{
    public function index(ListTasksRequest $request): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function store(StoreTaskRequest $request): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function show(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function update(UpdateTaskRequest $request): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }

    public function destroy(): Response
    {
        return response()->noContent(Response::HTTP_NOT_IMPLEMENTED);
    }
}
