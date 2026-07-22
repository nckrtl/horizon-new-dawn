<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs;

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
    ) {}

    public function page(int $afterIndex, ?string $tag = null): JobPageData
    {
        try {
            $tag = trim($tag ?? '');

            if ($tag === '') {
                $failed = $this->repository->getFailed((string) $afterIndex);
                $total = $this->repository->countFailed();
                $current = $afterIndex;
                $next = $failed->count() === self::PAGE_SIZE ? $this->lastIndex($failed) : null;
            } else {
                $current = max(0, $afterIndex);
                $ids = $this->tags->paginate("failed:{$tag}", $current, self::PAGE_SIZE);
                $jobIds = array_values(array_filter($ids, is_string(...)));
                $failed = $this->repository->getJobs($jobIds, $current);
                $total = $this->tags->count("failed:{$tag}");
                $next = count($ids) === self::PAGE_SIZE ? $current + self::PAGE_SIZE : null;
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
            $job = $this->repository->getJobs([$id])->first();

            if (! is_object($job)) {
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
                delayedUntil: $detail->delayedUntil,
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
