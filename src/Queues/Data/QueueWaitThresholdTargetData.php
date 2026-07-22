<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use NckRtl\HorizonNewDawn\Queues\QueueWaitThresholdStatus;
use Spatie\LaravelData\Data;

final class QueueWaitThresholdTargetData extends Data
{
    public function __construct(
        public readonly string $connection,
        public readonly QueueWaitThresholdStatus $status,
        public readonly bool $monitored,
        public readonly int|float|null $waitSeconds,
        public readonly int $thresholdSeconds,
        public readonly ?int $oldestReadyAgeSeconds,
    ) {}
}
