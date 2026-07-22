<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Supervisors\Actions;

use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use NckRtl\HorizonNewDawn\Supervisors\LocalSupervisor;
use RuntimeException;

final readonly class ContinueSupervisor
{
    public function __construct(
        private SupervisorRepository $supervisors,
        private HorizonCommandQueue $commands,
    ) {}

    public function handle(string $supervisor): void
    {
        $record = $this->findSupervisor($supervisor);

        if (! is_object($record) || ! LocalSupervisor::matches($record, $supervisor)) {
            throw new RuntimeException('The requested Horizon supervisor is not active.');
        }

        $this->commands->push($supervisor, ContinueWorking::class, []);
    }

    private function findSupervisor(string $supervisor): mixed
    {
        return $this->supervisors->find($supervisor);
    }
}
