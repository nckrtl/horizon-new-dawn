<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchPreviewData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueActivityPageData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRetainedBatchesData;
use Throwable;

final readonly class QueueBatchesData
{
    private const int SOURCE_PAGE_SIZE = 50;

    private const int RESULT_PAGE_SIZE = 50;

    private const int SCAN_LIMIT = 250;

    public function __construct(
        private BatchRepository $repository,
        private BatchesData $batches,
        private CacheFactory $cache,
    ) {}

    public function page(string $queue, ?string $beforeId): QueueActivityPageData
    {
        try {
            $cursor = $beforeId;
            $inspected = 0;
            $rows = [];
            $next = null;
            $complete = false;
            $message = null;

            while ($inspected < self::SCAN_LIMIT && count($rows) < self::RESULT_PAGE_SIZE) {
                $source = $this->repository->get(self::SOURCE_PAGE_SIZE, $cursor);

                if ($source === []) {
                    $complete = true;

                    break;
                }

                $lastId = null;

                foreach ($source as $batch) {
                    if ($inspected >= self::SCAN_LIMIT) {
                        break;
                    }

                    $inspected++;

                    $lastId = $batch->id;

                    if ($this->batches->queue($batch) !== $queue) {
                        continue;
                    }

                    $rows[] = $this->batches->row($batch);
                }

                if (count($source) < self::SOURCE_PAGE_SIZE) {
                    $complete = true;

                    break;
                }

                if ($lastId === null || $lastId === $cursor) {
                    $next = null;
                    $message = 'More retained entries may exist for this queue.';

                    break;
                }

                $next = $lastId;

                if ($inspected >= self::SCAN_LIMIT) {
                    break;
                }

                $cursor = $lastId;
            }

            if ($inspected >= self::SCAN_LIMIT) {
                $message = 'More retained entries may exist for this queue.';
            }

            return new QueueActivityPageData(
                available: true,
                rows: $rows,
                total: count($rows),
                complete: $complete,
                pageName: 'before_id',
                current: $beforeId,
                next: $next,
                message: $message,
            );
        } catch (Throwable $exception) {
            report($exception);

            return QueueActivityPageData::unavailable(
                'before_id',
                'Retained batches are currently unavailable.',
            );
        }
    }

    public function summary(string $queue): QueueRetainedBatchesData
    {
        $pollInterval = (int) config('horizon-new-dawn.poll_interval', 0);

        if ($pollInterval <= 0) {
            return $this->buildSummary($queue);
        }

        try {
            $cache = $this->cache->store();
            $cacheKey = $this->cacheKey($queue);
            $cacheSeconds = (int) ceil($pollInterval / 1000);
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

            return $this->buildSummary($queue);
        }
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
    private function summaryFromPayload(array $payload): ?QueueRetainedBatchesData
    {
        try {
            return QueueRetainedBatchesData::from($payload);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildSummary(string $queue): QueueRetainedBatchesData
    {
        try {
            $cursor = null;
            $inspected = 0;
            $total = 0;
            $active = 0;
            $previews = [];
            $complete = false;

            while ($inspected < self::SCAN_LIMIT) {
                $source = $this->repository->get(self::SOURCE_PAGE_SIZE, $cursor);

                if ($source === []) {
                    $complete = true;

                    break;
                }

                $lastId = null;

                foreach ($source as $batch) {
                    if ($inspected >= self::SCAN_LIMIT) {
                        break;
                    }

                    $inspected++;

                    $lastId = $batch->id;

                    if ($this->batches->queue($batch) !== $queue) {
                        continue;
                    }

                    $total++;

                    if (! $this->active($batch)) {
                        continue;
                    }

                    $active++;

                    if (count($previews) < 3) {
                        $row = $this->batches->row($batch);
                        $previews[] = new DashboardBatchPreviewData(
                            id: $row->id,
                            name: $row->displayName,
                            progress: $row->progress,
                        );
                    }
                }

                if (count($source) < self::SOURCE_PAGE_SIZE) {
                    $complete = true;

                    break;
                }

                if ($lastId === null || $lastId === $cursor) {
                    break;
                }

                $cursor = $lastId;
            }

            return new QueueRetainedBatchesData(
                total: $total,
                active: $active,
                previews: $previews,
                complete: $complete,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new QueueRetainedBatchesData(
                total: 0,
                active: 0,
                previews: [],
                complete: false,
                message: 'Retained batches are currently unavailable.',
            );
        }
    }

    private function active(Batch $batch): bool
    {
        return ! $batch->cancelled()
            && max(0, $batch->pendingJobs - $batch->failedJobs) > 0;
    }

    private function cacheKey(string $queue): string
    {
        $prefix = config('horizon.prefix', 'horizon:');
        $prefix = is_string($prefix) ? $prefix : 'horizon:';

        return 'horizon-new-dawn:queue-batches:'.hash('sha256', $prefix."\0".$queue);
    }
}
