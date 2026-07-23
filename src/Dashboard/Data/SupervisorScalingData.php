<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use NckRtl\HorizonNewDawn\Dashboard\SupervisorScalingState;
use Spatie\LaravelData\Data;

final class SupervisorScalingData extends Data
{
    public function __construct(
        public readonly int $readyJobs,
        public readonly SupervisorScalingState $state,
        public readonly string $strategy,
        public readonly int $targetProcesses,
    ) {}
}
