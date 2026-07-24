<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\FailedJobs\Data\FailedJobDetailData;
use NckRtl\HorizonNewDawn\FailedJobs\Data\FailedJobRetryData;
use NckRtl\HorizonNewDawn\Jobs\Data\JobPageData;
use NckRtl\HorizonNewDawn\Jobs\Data\JobRowData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use Throwable;

final readonly class FailedJobsData
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private JobRepository $repository,
        private TagRepository $tags,
        private JobsData $jobs,
        private FailedJobRetryEligibility $retryEligibility,
        private ?RedisFactory $redis = null,
    ) {}

    public function page(int $afterIndex, ?string $tag = null): JobPageData
    {
        try {
            $tag = trim($tag ?? '');

            if ($tag === '') {
                $page = $this->oldestFailed($afterIndex);
                $failed = $page['jobs'];
                $total = $this->repository->countFailed();
                $current = $afterIndex;
                $next = $page['next'];
            } else {
                $current = max(0, $afterIndex);
                $ids = $this->oldestTaggedFailed($tag, $current);
                $hasMore = count($ids) > self::PAGE_SIZE;
                $jobIds = array_values(array_filter(
                    array_slice($ids, 0, self::PAGE_SIZE),
                    is_string(...),
                ));
                $failed = $this->repository->getJobs($jobIds, $current);
                $total = $this->tags->count("failed:{$tag}");
                $next = $hasMore ? $current + self::PAGE_SIZE : null;
            }

            $items = [];

            foreach ($failed as $job) {
                if (! is_object($job)) {
                    continue;
                }

                $row = $this->row($job);

                if ($row !== null) {
                    $items[] = $row;
                }
            }

            return new JobPageData(
                available: true,
                items: $items,
                total: $total,
                current: $current,
                next: $next,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new JobPageData(
                available: false,
                items: [],
                total: 0,
                current: $afterIndex,
                next: null,
                message: 'Failed jobs are currently unavailable.',
            );
        }
    }

    public function hasRetryable(): bool
    {
        try {
            if ($this->redis !== null) {
                return $this->hasRetryableFromRawIndex();
            }

            $afterIndex = -1;

            while (true) {
                $failed = $this->repository->getFailed((string) $afterIndex);

                if ($failed->isEmpty()) {
                    return false;
                }

                foreach ($failed as $job) {
                    if (is_object($job) && $this->retryEligibility->allowsBulk($job)) {
                        return true;
                    }
                }

                if ($failed->count() < self::PAGE_SIZE) {
                    return false;
                }

                $nextIndex = $this->lastIndex($failed);

                if ($nextIndex === null || $nextIndex <= $afterIndex) {
                    return false;
                }

                $afterIndex = $nextIndex;
            }
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function row(object $job): ?JobRowData
    {
        $retries = $this->retries($job);

        return $this->jobs->row(
            $job,
            retried: $retries !== [],
            retryCompleted: $this->hasCompletedRetry($retries),
            retryCount: count($retries),
            latestRetryStatus: $retries[0]->status ?? null,
            retryEligible: $this->retryEligibility->allows($job),
        );
    }

    public function find(string $id): ?FailedJobDetailData
    {
        try {
            $job = $this->repository->findFailed($id);

            if (! is_object($job) || ($job->status ?? null) !== 'failed') {
                return null;
            }

            $detail = $this->jobs->detail($job);

            if ($detail === null) {
                return null;
            }

            return new FailedJobDetailData(
                id: $detail->id,
                name: $detail->name,
                shortName: $detail->shortName,
                connection: $detail->connection,
                queue: $detail->queue,
                status: $detail->status,
                tags: $detail->tags,
                pushedAt: $detail->pushedAt,
                reservedAt: $detail->reservedAt,
                failedAt: $detail->failedAt,
                runtime: $detail->runtime,
                attempts: $detail->attempts,
                retryOf: $detail->retryOf,
                delay: $detail->delay,
                scheduledAt: $detail->scheduledAt,
                originalScheduledAt: $detail->originalScheduledAt,
                batchId: $detail->batchId,
                retried: $this->wasRetried($job),
                retriedBy: $this->retries($job),
                retryEligible: $this->retryEligibility->allows($job),
                payload: $detail->payload,
                context: $this->context($job->context ?? null),
                exception: is_string($job->exception ?? null)
                    ? mb_convert_encoding($job->exception, 'UTF-8', 'UTF-8')
                    : '',
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return array{jobs: Collection<int, mixed>, next: int|null}
     */
    private function oldestFailed(int $afterIndex): array
    {
        if ($this->redis === null) {
            $failed = $this->repository->getFailed((string) $afterIndex);

            return [
                'jobs' => $failed,
                'next' => $failed->count() === self::PAGE_SIZE ? $this->lastIndex($failed) : null,
            ];
        }

        $start = $afterIndex + 1;
        $ids = $this->redis->connection('horizon')->zrevrange(
            'failed_jobs',
            $start,
            $start + self::PAGE_SIZE,
        );

        if (! is_array($ids)) {
            return ['jobs' => new Collection, 'next' => null];
        }

        $hasMore = count($ids) > self::PAGE_SIZE;
        $ids = array_slice($ids, 0, self::PAGE_SIZE);

        return [
            'jobs' => $this->repository->getJobs(
                array_values(array_filter($ids, is_string(...))),
                $start,
            ),
            'next' => $hasMore ? $start + self::PAGE_SIZE - 1 : null,
        ];
    }

    /** @return array<int, mixed> */
    private function oldestTaggedFailed(string $tag, int $startingAt): array
    {
        if ($this->redis === null) {
            return $this->tags->paginate("failed:{$tag}", $startingAt, self::PAGE_SIZE + 1);
        }

        $ids = $this->redis->connection('horizon')->zrange(
            "failed:{$tag}",
            $startingAt,
            $startingAt + self::PAGE_SIZE,
        );

        return is_array($ids) ? array_values($ids) : [];
    }

    private function hasRetryableFromRawIndex(): bool
    {
        $startingAt = 0;
        $connection = $this->redis?->connection('horizon');

        if ($connection === null) {
            return false;
        }

        while (true) {
            $ids = $connection->zrevrange(
                'failed_jobs',
                $startingAt,
                $startingAt + self::PAGE_SIZE,
            );

            if (! is_array($ids) || $ids === []) {
                return false;
            }

            $hasMore = count($ids) > self::PAGE_SIZE;
            $pageIds = array_values(array_filter(
                array_slice($ids, 0, self::PAGE_SIZE),
                is_string(...),
            ));
            $failed = $this->repository->getJobs($pageIds, $startingAt);

            foreach ($failed as $job) {
                if (is_object($job) && $this->retryEligibility->allowsBulk($job)) {
                    return true;
                }
            }

            if (! $hasMore) {
                return false;
            }

            $startingAt += self::PAGE_SIZE;
        }
    }

    /** @param Collection<int, mixed> $jobs */
    private function lastIndex(Collection $jobs): ?int
    {
        $last = $jobs->last();

        return is_object($last) && is_numeric($last->index ?? null) ? (int) $last->index : null;
    }

    private function wasRetried(object $job): bool
    {
        return $this->retries($job) !== [];
    }

    /** @param array<int, FailedJobRetryData> $retries */
    private function hasCompletedRetry(array $retries): bool
    {
        foreach ($retries as $retry) {
            if ($retry->status === 'completed') {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, FailedJobRetryData> */
    private function retries(object $job): array
    {
        $retriedBy = $job->retried_by ?? null;

        if (is_array($retriedBy)) {
            return $this->normalizeRetries($retriedBy);
        }

        if (! is_string($retriedBy) || $retriedBy === '') {
            return [];
        }

        try {
            $decoded = json_decode($retriedBy, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $this->normalizeRetries($decoded) : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @param  array<array-key, mixed>  $retries
     * @return array<int, FailedJobRetryData>
     */
    private function normalizeRetries(array $retries): array
    {
        $normalized = [];

        foreach ($retries as $retry) {
            if (! is_array($retry) || ! is_string($retry['id'] ?? null) || $retry['id'] === '') {
                continue;
            }

            $normalized[] = new FailedJobRetryData(
                id: $retry['id'],
                status: is_string($retry['status'] ?? null) ? $retry['status'] : 'unknown',
                retriedAt: is_numeric($retry['retried_at'] ?? null) ? (float) $retry['retried_at'] : null,
            );
        }

        usort(
            $normalized,
            static fn (FailedJobRetryData $left, FailedJobRetryData $right): int => ($right->retriedAt ?? 0) <=> ($left->retriedAt ?? 0),
        );

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function context(mixed $context): array
    {
        if (! is_string($context) || $context === '') {
            return [];
        }

        try {
            $decoded = json_decode($context, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}
