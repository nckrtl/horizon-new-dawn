<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Metrics\Data;

use Spatie\LaravelData\Data;

final class MetricPreviewData extends Data
{
    /** @param array<int, MetricSnapshotData> $snapshots */
    public function __construct(
        public readonly bool $available,
        public readonly string $name,
        public readonly array $snapshots,
        public readonly ?string $message,
    ) {}
}
