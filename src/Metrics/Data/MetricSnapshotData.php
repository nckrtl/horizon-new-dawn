<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Metrics\Data;

use Spatie\LaravelData\Data;

final class MetricSnapshotData extends Data
{
    public function __construct(
        public readonly int $timestamp,
        public readonly int $throughput,
        public readonly ?float $runtime,
    ) {}
}
