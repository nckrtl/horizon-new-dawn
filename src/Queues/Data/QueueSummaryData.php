<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchPreviewData;
use Spatie\LaravelData\Data;

final class QueueSummaryData extends Data
{
    /**
     * @param  array<int, string>  $connections
     * @param  array<int, QueuePauseTargetData>  $pauseTargets
     * @param  array<int, DashboardBatchPreviewData>  $batchPreviews
     */
    public function __construct(
        public readonly bool $available,
        public readonly string $name,
        public readonly array $connections,
        public readonly array $pauseTargets,
        public readonly ?int $pendingJobs,
        public readonly bool $pendingComplete,
        public readonly ?int $pendingReserved,
        public readonly ?int $pendingReadyNow,
        public readonly ?int $pendingDelayed,
        public readonly ?int $failedJobs,
        public readonly bool $failedComplete,
        public readonly ?float $failedJobsPerMinute,
        public readonly bool $failedJobsPerMinuteComplete,
        public readonly ?int $failedJobsPastHour,
        public readonly bool $failedJobsPastHourComplete,
        public readonly ?int $failedJobsPastDay,
        public readonly bool $failedJobsPastDayComplete,
        public readonly int $failedRetentionMinutes,
        public readonly ?int $completedJobs,
        public readonly bool $completedComplete,
        public readonly ?float $completedJobsPerMinute,
        public readonly bool $completedJobsPerMinuteComplete,
        public readonly ?int $completedJobsPastHour,
        public readonly bool $completedJobsPastHourComplete,
        public readonly ?int $completedJobsPastDay,
        public readonly bool $completedJobsPastDayComplete,
        public readonly int $completedRetentionMinutes,
        public readonly ?int $silencedJobs,
        public readonly bool $silencedComplete,
        public readonly ?int $batches,
        public readonly ?int $activeBatches,
        public readonly bool $batchesComplete,
        public readonly array $batchPreviews,
        public readonly ?int $processes,
        public readonly ?QueueWaitThresholdData $waitThreshold,
        public readonly ?int $throughput,
        public readonly ?float $averageRuntime,
        public readonly ?string $message,
    ) {}
}
