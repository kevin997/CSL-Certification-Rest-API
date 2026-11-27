<?php

namespace App\Jobs;

use App\Services\Workflows\WorkflowRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAcademyWorkflows implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $trigger, public int $environmentId, public int $subjectId, public int $observedAtUs) {}

    public function handle(WorkflowRunner $runner): void
    {
        $runner->run($this->trigger, $this->environmentId, $this->subjectId, $this->observedAtUs);
    }
}
