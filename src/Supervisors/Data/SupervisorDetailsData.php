<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Supervisors\Data;

use Spatie\LaravelData\Data;

final class SupervisorDetailsData extends Data
{
    /**
     * @param  array<int, string>  $queues
     * @param  array<int, SupervisorWarningData>  $warnings
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $master,
        public readonly ?int $pid,
        public readonly string $status,
        public readonly string $connection,
        public readonly array $queues,
        public readonly int $processes,
        public readonly ?string $balance,
        public readonly ?string $autoScalingStrategy,
        public readonly ?int $minProcesses,
        public readonly ?int $maxProcesses,
        public readonly ?int $balanceCooldown,
        public readonly ?int $balanceMaxShift,
        public readonly ?int $memory,
        public readonly ?int $timeout,
        public readonly ?int $retryAfter,
        public readonly ?int $maxTries,
        public readonly ?int $backoff,
        public readonly ?int $maxJobs,
        public readonly ?int $maxTime,
        public readonly ?int $sleep,
        public readonly ?int $rest,
        public readonly ?bool $force,
        public readonly ?int $nice,
        public readonly array $warnings,
    ) {}
}
