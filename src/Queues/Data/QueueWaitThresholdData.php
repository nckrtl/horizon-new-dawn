<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use NckRtl\HorizonNewDawn\Queues\QueueWaitThresholdStatus;
use Spatie\LaravelData\Data;

final class QueueWaitThresholdData extends Data
{
    /** @param array<int, QueueWaitThresholdTargetData> $targets */
    public function __construct(
        public readonly QueueWaitThresholdStatus $status,
        public readonly string $decisiveConnection,
        public readonly int|float|null $waitSeconds,
        public readonly int $thresholdSeconds,
        public readonly ?int $oldestReadyAgeSeconds,
        public readonly ?string $oldestReadyConnection,
        public readonly array $targets,
    ) {}
}
