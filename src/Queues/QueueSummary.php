<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Laravel\Horizon\Contracts\MetricsRepository;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRowData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueSummaryData;
use Throwable;

final readonly class QueueSummary
{
    public function __construct(
        private QueueJobsData $jobs,
        private QueueBatchesData $batches,
        private MetricsRepository $metrics,
    ) {}

    public function forQueue(QueueRowData $queue): QueueSummaryData
    {
        $jobs = $this->jobs->summary($queue->name);
        $batches = $this->batches->summary($queue->name);
        [$throughput, $averageRuntime] = $this->snapshotMetrics($queue->name);

        return new QueueSummaryData(
            available: true,
            name: $queue->name,
            connections: $queue->connections,
            pauseTargets: $queue->pauseTargets,
            pendingJobs: $jobs->pending,
            pendingComplete: $jobs->pendingComplete,
            pendingReserved: $queue->reserved,
            pendingReadyNow: $queue->ready,
            pendingDelayed: $queue->delayed,
            failedJobs: $jobs->failed,
            failedComplete: $jobs->failedComplete,
            failedJobsPerMinute: $jobs->failedPerMinute,
            failedJobsPerMinuteComplete: $jobs->failedPerMinuteComplete,
            failedJobsPastHour: $jobs->failedPastHour,
            failedJobsPastHourComplete: $jobs->failedPastHourComplete,
            failedJobsPastDay: $jobs->failedPastDay,
            failedJobsPastDayComplete: $jobs->failedPastDayComplete,
            failedRetentionMinutes: $jobs->failedRetentionMinutes,
            completedJobs: $jobs->completed,
            completedComplete: $jobs->completedComplete,
            completedJobsPerMinute: $jobs->completedPerMinute,
            completedJobsPerMinuteComplete: $jobs->completedPerMinuteComplete,
            completedJobsPastHour: $jobs->completedPastHour,
            completedJobsPastHourComplete: $jobs->completedPastHourComplete,
            completedJobsPastDay: $jobs->completedPastDay,
            completedJobsPastDayComplete: $jobs->completedPastDayComplete,
            completedRetentionMinutes: $jobs->completedRetentionMinutes,
            silencedJobs: $jobs->silenced,
            silencedComplete: $jobs->silencedComplete,
            batches: $batches->total,
            activeBatches: $batches->active,
            batchesComplete: $batches->complete,
            batchPreviews: $batches->previews,
            processes: $queue->processes,
            waitThreshold: $queue->waitThreshold,
            throughput: $throughput,
            averageRuntime: $averageRuntime,
            message: $this->partialDataMessage($jobs->message, $batches->message),
        );
    }

    public static function unavailable(string $queue, string $message): QueueSummaryData
    {
        return new QueueSummaryData(
            available: false,
            name: $queue,
            connections: [],
            pauseTargets: [],
            pendingJobs: null,
            pendingComplete: false,
            pendingReserved: null,
            pendingReadyNow: null,
            pendingDelayed: null,
            failedJobs: null,
            failedComplete: false,
            failedJobsPerMinute: null,
            failedJobsPerMinuteComplete: false,
            failedJobsPastHour: null,
            failedJobsPastHourComplete: false,
            failedJobsPastDay: null,
            failedJobsPastDayComplete: false,
            failedRetentionMinutes: max(0, (int) config('horizon.trim.failed', 10080)),
            completedJobs: null,
            completedComplete: false,
            completedJobsPerMinute: null,
            completedJobsPerMinuteComplete: false,
            completedJobsPastHour: null,
            completedJobsPastHourComplete: false,
            completedJobsPastDay: null,
            completedJobsPastDayComplete: false,
            completedRetentionMinutes: max(0, (int) config('horizon.trim.completed', 60)),
            silencedJobs: null,
            silencedComplete: false,
            batches: null,
            activeBatches: null,
            batchesComplete: false,
            batchPreviews: [],
            processes: null,
            waitThreshold: null,
            throughput: null,
            averageRuntime: null,
            message: $message,
        );
    }

    /** @return array{0: ?int, 1: ?float} */
    private function snapshotMetrics(string $queue): array
    {
        try {
            $throughput = $this->metrics->throughputForQueue($queue);
            $averageRuntime = $throughput > 0
                ? round($this->metrics->runtimeForQueue($queue) / 1000, 3)
                : null;

            return [$throughput, $averageRuntime];
        } catch (Throwable $exception) {
            report($exception);

            return [null, null];
        }
    }

    private function partialDataMessage(?string ...$messages): ?string
    {
        $messages = array_values(array_unique(array_filter($messages)));

        return $messages === [] ? null : implode(' ', $messages);
    }
}
