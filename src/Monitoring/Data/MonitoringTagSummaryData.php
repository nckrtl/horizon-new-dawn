<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Data;

use Spatie\LaravelData\Data;

final class MonitoringTagSummaryData extends Data
{
    public function __construct(
        public readonly string $tag,
        public readonly int $trackedCount,
        public readonly int $failedCount,
        public readonly bool $silenced,
        public readonly int $monitoredRetentionMinutes,
        public readonly int $failedRetentionMinutes,
    ) {}
}
