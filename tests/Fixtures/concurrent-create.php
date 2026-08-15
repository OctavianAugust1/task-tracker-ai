<?php

use App\Services\JsonTaskStore;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$store = new JsonTaskStore($argv[1]);
$store->create(['title' => $argv[2]]);
