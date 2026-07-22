<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class QueueRowData extends Data
{
    /**
     * @param  array<int, string>  $connections
     * @param  array<int, QueuePauseTargetData>  $pauseTargets
     */
    public function __construct(
        public readonly string $name,
        public readonly array $connections,
        public readonly array $pauseTargets,
        public readonly int $ready,
        public readonly int $reserved,
        public readonly int $delayed,
        public readonly int $processes,
        public readonly int|float $wait,
        public readonly QueueWaitThresholdData $waitThreshold,
    ) {}
}
