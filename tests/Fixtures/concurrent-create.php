<?php

declare(strict_types=1);

use App\Repositories\JsonTaskRepository;
use App\Services\TaskService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$title = $argv[2] ?? '';

if ($title === '') {
    throw new InvalidArgumentException('A non-empty task title is required.');
}

$service = new TaskService(new JsonTaskRepository($argv[1]));
$service->create(['title' => $title]);
