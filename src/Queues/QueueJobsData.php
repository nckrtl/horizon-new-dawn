<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\Data\JobRowData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueActivityPageData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRetainedJobsData;
use Throwable;

final readonly class QueueJobsData
{
    private const int SOURCE_PAGE_SIZE = 50;

    private const int RESULT_PAGE_SIZE = 50;

    private const int SCAN_LIMIT = 250;

    public function __construct(
        private JobRepository $repository,
        private JobsData $jobs,
        private FailedJobsData $failedJobs,
        private CacheFactory $cache,
    ) {}

    public function page(
        string $queue,
        QueueActivityTab $tab,
        int $afterIndex,
    ): QueueActivityPageData {
        if ($tab === QueueActivityTab::Batches) {
            return QueueActivityPageData::unavailable(
                'before_id',
                'Retained batches are currently unavailable.',
            );
        }

        try {
            $cursor = $afterIndex;
            $retainedReferenceCount = $this->retainedReferenceCount($tab);
            $scanned = 0;
            $rows = [];
            $next = null;
            $exhausted = $cursor >= $retainedReferenceCount - 1;
            $fullyHydrated = $afterIndex === -1;
            $message = null;

            while (! $exhausted && $scanned < self::SCAN_LIMIT && count($rows) < self::RESULT_PAGE_SIZE) {
                $rawPageSize = min(
                    self::SOURCE_PAGE_SIZE,
                    self::SCAN_LIMIT - $scanned,
                    $retainedReferenceCount - ($cursor + 1),
                );
                $source = $this->source($tab, $cursor);
                $fullyHydrated = $fullyHydrated && $source->count() === $rawPageSize;

                foreach ($source->take($rawPageSize) as $job) {
                    if (! is_object($job)) {
                        continue;
                    }

                    if (($job->queue ?? null) !== $queue) {
                        continue;
                    }

                    $row = $this->row($tab, $job);

                    if ($row !== null) {
                        $rows[] = $row;
                    }
                }

                $scanned += $rawPageSize;
                $cursor += $rawPageSize;
                $exhausted = $cursor >= $retainedReferenceCount - 1;
                $next = $exhausted ? null : $cursor;
            }

            $complete = $exhausted && $fullyHydrated;

            if (! $complete && ($exhausted || $scanned >= self::SCAN_LIMIT)) {
                $message = 'More retained entries may exist for this queue.';
            }

            return new QueueActivityPageData(
                available: true,
                rows: $rows,
                total: count($rows),
                complete: $complete,
                pageName: 'starting_at',
                current: $afterIndex,
                next: $next,
                message: $message,
            );
        } catch (Throwable $exception) {
            report($exception);

            return QueueActivityPageData::unavailable(
                'starting_at',
                "Retained {$tab->value} jobs are currently unavailable.",
            );
        }
    }

    public function summary(string $queue): QueueRetainedJobsData
    {
        $pollInterval = (int) config('horizon-new-dawn.poll_interval', 0);

        if ($pollInterval <= 0) {
            return $this->buildSummary($queue);
        }

        $cacheSeconds = intdiv($pollInterval, 1000);

        if ($cacheSeconds === 0) {
            return $this->buildSummary($queue);
        }

        try {
            $cache = $this->cache->store();
            $cacheKey = $this->cacheKey($queue);
            $payload = $this->rememberSummaryPayload($queue, $cacheSeconds);
            $cached = is_array($payload) ? $this->summaryFromPayload($payload) : null;

            if ($cached !== null) {
                return $cached;
            }

            $cache->forget($cacheKey);
            $payload = $this->rememberSummaryPayload($queue, $cacheSeconds);
            $cached = is_array($payload) ? $this->summaryFromPayload($payload) : null;

            return $cached ?? $this->buildSummary($queue);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->buildSummary($queue);
    }

    private function rememberSummaryPayload(string $queue, int $cacheSeconds): mixed
    {
        return $this->cache->store()->remember(
            $this->cacheKey($queue),
            $cacheSeconds,
            fn (): array => $this->buildSummary($queue)->toArray(),
        );
    }

    /** @param array<string, mixed> $payload */
    private function summaryFromPayload(array $payload): ?QueueRetainedJobsData
    {
        try {
            return QueueRetainedJobsData::from($payload);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildSummary(string $queue): QueueRetainedJobsData
    {
        $pending = $this->safeSummary($queue, QueueActivityTab::Pending);
        $completed = $this->safeSummary($queue, QueueActivityTab::Completed);
        $failed = $this->safeSummary($queue, QueueActivityTab::Failed);
        $silenced = $this->safeSummary($queue, QueueActivityTab::Silenced);
        $available = $pending['available']
            && $completed['available']
            && $failed['available']
            && $silenced['available'];
        $completedRetentionMinutes = $this->retentionMinutes(QueueActivityTab::Completed);
        $failedRetentionMinutes = $this->retentionMinutes(QueueActivityTab::Failed);

        return new QueueRetainedJobsData(
            pending: $pending['total'],
            pendingComplete: $pending['complete'],
            completed: $completed['total'],
            completedComplete: $completed['complete'],
            completedPerMinute: round(
                $completed['hour'] / max(1, min(60, $completedRetentionMinutes)),
                2,
            ),
            completedPerMinuteComplete: $completed['complete'],
            completedPastHour: $completed['hour'],
            completedPastHourComplete: $completed['complete'],
            completedPastDay: $completed['day'],
            completedPastDayComplete: $completed['complete'],
            completedRetentionMinutes: $completedRetentionMinutes,
            failed: $failed['total'],
            failedComplete: $failed['complete'],
            failedPerMinute: round(
                $failed['hour'] / max(1, min(60, $failedRetentionMinutes)),
                2,
            ),
            failedPerMinuteComplete: $failed['complete'],
            failedPastHour: $failed['hour'],
            failedPastHourComplete: $failed['complete'],
            failedPastDay: $failed['day'],
            failedPastDayComplete: $failed['complete'],
            failedRetentionMinutes: $failedRetentionMinutes,
            silenced: $silenced['total'],
            silencedComplete: $silenced['complete'],
            message: $available ? null : 'Some retained job data is currently unavailable.',
        );
    }

    /** @return array{total: int, complete: bool, hour: int, day: int, available: bool} */
    private function safeSummary(string $queue, QueueActivityTab $tab): array
    {
        try {
            return $this->scanSummary($queue, $tab);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'total' => 0,
                'complete' => false,
                'hour' => 0,
                'day' => 0,
                'available' => false,
            ];
        }
    }

    /** @return array{total: int, complete: bool, hour: int, day: int, available: bool} */
    private function scanSummary(string $queue, QueueActivityTab $tab): array
    {
        $cursor = -1;
        $retainedReferenceCount = $this->retainedReferenceCount($tab);
        $scanned = 0;
        $total = 0;
        $hour = 0;
        $day = 0;
        $exhausted = $retainedReferenceCount === 0;
        $fullyHydrated = true;
        $retentionMinutes = $this->retentionMinutes($tab);
        $hourCutoff = CarbonImmutable::now()
            ->subMinutes(min(60, $retentionMinutes))
            ->timestamp;
        $dayCutoff = CarbonImmutable::now()
            ->subMinutes(min(1440, $retentionMinutes))
            ->timestamp;

        while (! $exhausted && $scanned < self::SCAN_LIMIT) {
            $rawPageSize = min(
                self::SOURCE_PAGE_SIZE,
                self::SCAN_LIMIT - $scanned,
                $retainedReferenceCount - ($cursor + 1),
            );
            $source = $this->source($tab, $cursor);
            $fullyHydrated = $fullyHydrated && $source->count() === $rawPageSize;

            foreach ($source->take($rawPageSize) as $job) {
                if (! is_object($job)) {
                    continue;
                }

                if (($job->queue ?? null) !== $queue) {
                    continue;
                }

                $total++;
                $timestamp = $this->periodTimestamp($tab, $job);

                if ($timestamp !== null && $timestamp >= $dayCutoff) {
                    $day++;
                }

                if ($timestamp !== null && $timestamp >= $hourCutoff) {
                    $hour++;
                }
            }

            $scanned += $rawPageSize;
            $cursor += $rawPageSize;
            $exhausted = $cursor >= $retainedReferenceCount - 1;
        }

        return [
            'total' => $total,
            'complete' => $exhausted && $fullyHydrated,
            'hour' => $hour,
            'day' => $day,
            'available' => true,
        ];
    }

    /** @return Collection<int, mixed> */
    private function source(QueueActivityTab $tab, int $cursor): Collection
    {
        $jobs = match ($tab) {
            QueueActivityTab::Pending => $this->repository->getPending((string) $cursor),
            QueueActivityTab::Completed => $this->repository->getCompleted((string) $cursor),
            QueueActivityTab::Failed => $this->repository->getFailed((string) $cursor),
            QueueActivityTab::Silenced => $this->repository->getSilenced((string) $cursor),
            QueueActivityTab::Batches => collect(),
        };

        return $jobs;
    }

    private function retainedReferenceCount(QueueActivityTab $tab): int
    {
        $count = match ($tab) {
            QueueActivityTab::Pending => $this->repository->countPending(),
            QueueActivityTab::Completed => $this->repository->countCompleted(),
            QueueActivityTab::Failed => $this->repository->countFailed(),
            QueueActivityTab::Silenced => $this->repository->countSilenced(),
            QueueActivityTab::Batches => 0,
        };

        return max(0, (int) $count);
    }

    private function row(QueueActivityTab $tab, object $job): ?JobRowData
    {
        return $tab === QueueActivityTab::Failed
            ? $this->failedJobs->row($job)
            : $this->jobs->row($job);
    }

    private function periodTimestamp(QueueActivityTab $tab, object $job): ?int
    {
        $value = match ($tab) {
            QueueActivityTab::Pending => $job->reserved_at ?? null,
            QueueActivityTab::Completed => $job->completed_at ?? null,
            QueueActivityTab::Failed => $job->failed_at ?? null,
            QueueActivityTab::Silenced => $job->completed_at ?? null,
            QueueActivityTab::Batches => null,
        };

        return is_numeric($value) ? (int) $value : null;
    }

    private function cacheKey(string $queue): string
    {
        $prefix = config('horizon.prefix', 'horizon:');
        $prefix = is_string($prefix) ? $prefix : 'horizon:';

        return 'horizon-new-dawn:queue-jobs:'.hash('sha256', $prefix."\0".$queue);
    }

    private function retentionMinutes(QueueActivityTab $tab): int
    {
        $minutes = match ($tab) {
            QueueActivityTab::Completed, QueueActivityTab::Silenced => config('horizon.trim.completed', 60),
            QueueActivityTab::Failed => config('horizon.trim.failed', 10080),
            QueueActivityTab::Pending => config('horizon.trim.pending', 60),
            QueueActivityTab::Batches => 0,
        };

        return max(0, (int) $minutes);
    }
}
