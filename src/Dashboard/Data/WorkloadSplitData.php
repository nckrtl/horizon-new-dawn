<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use NckRtl\HorizonNewDawn\Queues\Data\QueueWaitThresholdData;
use Spatie\LaravelData\Data;

final class WorkloadSplitData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $length,
        public readonly int|float $wait,
        public readonly bool $paused,
        public readonly ?int $pausedUntil,
        public readonly QueueWaitThresholdData $waitThreshold,
    ) {}
}
