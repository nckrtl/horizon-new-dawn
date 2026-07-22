<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Metrics\Data;

use Spatie\LaravelData\Data;

final class MetricRowData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $throughput,
        public readonly float $runtime,
    ) {}
}
