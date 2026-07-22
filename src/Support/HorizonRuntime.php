<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support;

use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Dashboard\DashboardPendingState;
use NckRtl\HorizonNewDawn\Dashboard\HorizonStatus;
use Throwable;

final readonly class HorizonRuntime
{
    public function __construct(
        private MasterSupervisorRepository $masters,
        private ?WorkloadRepository $workload = null,
        private ?DashboardPendingState $pendingState = null,
        private ?WaitTimeCalculator $waitTimes = null,
    ) {}

    public function status(): HorizonStatus
    {
        try {
            $masters = $this->masters->all();

            return match (true) {
                $masters === [] => HorizonStatus::Inactive,
                collect($masters)->every(
                    static fn (object $master): bool => ($master->status ?? null) === 'paused',
                ) => HorizonStatus::Paused,
                default => HorizonStatus::Running,
            };
        } catch (Throwable $exception) {
            report($exception);

            return HorizonStatus::Unavailable;
        }
    }

    public function isProcessing(HorizonStatus $status): bool
    {
        if (
            $status !== HorizonStatus::Running
            || $this->workload === null
            || $this->pendingState === null
            || $this->waitTimes === null
        ) {
            return false;
        }

        try {
            $hasActiveProcesses = collect($this->workload->get())->contains(
                static fn (array $queue): bool => $queue['processes'] > 0,
            );

            if (! $hasActiveProcesses) {
                return false;
            }

            $pending = $this->pendingState->forQueues($this->waitTimes->calculate());

            return ($pending->reserved ?? 0) > 0 || ($pending->readyNow ?? 0) > 0;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
