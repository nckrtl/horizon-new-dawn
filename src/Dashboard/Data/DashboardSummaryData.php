<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use NckRtl\HorizonNewDawn\Dashboard\HorizonStatus;
use Spatie\LaravelData\Data;

final class DashboardSummaryData extends Data
{
    /**
     * @param  array<int, DashboardBatchPreviewData>  $batchPreviews
     * @param  array<string, int|float>  $waits
     */
    public function __construct(
        public readonly bool $available,
        public readonly HorizonStatus $status,
        public readonly int $failedJobs,
        public readonly int $completedJobs,
        public readonly int $pendingJobs,
        public readonly ?int $pendingReserved,
        public readonly ?int $pendingReadyNow,
        public readonly ?int $pendingDelayed,
        public readonly int|float $failedJobsPerMinute,
        public readonly int $failedJobsPastHour,
        public readonly int $failedJobsPastDay,
        public readonly int $failedRetentionMinutes,
        public readonly int|float $completedJobsPerMinute,
        public readonly int $completedJobsPastHour,
        public readonly int $completedJobsPastDay,
        public readonly int $completedRetentionMinutes,
        public readonly int $activeBatches,
        public readonly array $batchPreviews,
        public readonly int $processes,
        public readonly array $waits,
        public readonly ?string $maxWaitQueue,
        public readonly int|float $maxWaitSeconds,
        public readonly ?string $queueWithMaxRuntime,
        public readonly ?string $queueWithMaxThroughput,
        public readonly ?string $message,
    ) {}
}
