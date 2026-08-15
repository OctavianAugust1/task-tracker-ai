<?php

namespace Tests\Unit;

use App\Services\JsonTaskStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class JsonTaskStoreConcurrencyTest extends TestCase
{
    private string $temporaryDirectory;

    private string $tasksFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/task-concurrency-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->tasksFile = $this->temporaryDirectory.'/tasks.json';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $temporaryFile) {
            unlink($temporaryFile);
        }

        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_parallel_creates_keep_every_task_and_assign_unique_ids(): void
    {
        $processes = [];

        for ($number = 1; $number <= 12; $number++) {
            $process = new Process([
                PHP_BINARY,
                dirname(__DIR__).'/Fixtures/concurrent-create.php',
                $this->tasksFile,
                'Task '.$number,
            ]);
            $process->start();
            $processes[] = $process;
        }

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $tasks = (new JsonTaskStore($this->tasksFile))->all();
        $ids = array_column($tasks, 'id');

        $this->assertCount(12, $tasks);
        $this->assertSame(range(1, 12), $ids);
        $this->assertCount(12, array_unique(array_column($tasks, 'title')));

        $state = json_decode(
            file_get_contents($this->tasksFile),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(13, $state['next_id']);
        $this->assertSame([], glob($this->temporaryDirectory.'/.tasks-*') ?: []);
    }
}
