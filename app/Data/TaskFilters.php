<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TaskFilters
{
    /** @param list<'todo'|'in_progress'|'done'> $statuses */
    public function __construct(
        public array $statuses = [],
        public ?string $dueDate = null,
    ) {}
}
