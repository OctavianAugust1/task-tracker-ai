<?php

use App\Repositories\JsonTaskRepository;
use App\Services\TaskService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$service = new TaskService(new JsonTaskRepository($argv[1]));
$service->create(['title' => $argv[2]]);
