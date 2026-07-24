<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Batches\Data\BatchClearCountsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use RuntimeException;
use Throwable;

final readonly class ClearableBatches
{
    private const int BATCH_PAGE_SIZE = 1000;

    private const int JOB_PAGE_SIZE = 50;

    public function __construct(
        private BatchRepository $batches,
        private JobRepository $jobs,
        private JobsData $jobData,
    ) {}

    public function counts(): BatchClearCountsData
    {
        try {
            $ids = $this->classifiedIds();
        } catch (Throwable $exception) {
            report($exception);

            return new BatchClearCountsData(
                incomplete: 0,
                complete: 0,
                finished: 0,
                cancelled: 0,
                available: false,
            );
        }

        $complete = count($ids[BatchClearScope::Complete->value]);
        $incomplete = count($ids[BatchClearScope::Incomplete->value]);
        $cancelled = count($ids[BatchClearScope::Cancelled->value]);

        return new BatchClearCountsData(
            incomplete: $incomplete,
            complete: $complete,
            finished: $complete + $incomplete,
            cancelled: $cancelled,
            available: true,
        );
    }

    /** @return array<int, string> */
    public function ids(BatchClearScope $scope): array
    {
        $ids = $this->classifiedIds();

        return match ($scope) {
            BatchClearScope::Incomplete => $ids[BatchClearScope::Incomplete->value],
            BatchClearScope::Complete => $ids[BatchClearScope::Complete->value],
            BatchClearScope::Cancelled => $ids[BatchClearScope::Cancelled->value],
            BatchClearScope::Finished => [
                ...$ids[BatchClearScope::Complete->value],
                ...$ids[BatchClearScope::Incomplete->value],
            ],
        };
    }

    /**
     * @return array{complete: array<int, string>, incomplete: array<int, string>, cancelled: array<int, string>}
     */
    private function classifiedIds(): array
    {
        $complete = [];
        $incomplete = [];
        $cancelled = [];

        foreach ($this->allBatches() as $batch) {
            if ($batch->cancelled()) {
                if ($batch->pendingJobs === 0) {
                    $cancelled[] = $batch->id;
                }

                continue;
            }

            if ($batch->finishedAt !== null) {
                $complete[] = $batch->id;

                continue;
            }

            if ($this->hasOnlyFailedJobsPending($batch)) {
                $incomplete[] = $batch->id;
            }
        }

        $candidates = array_fill_keys([...$complete, ...$incomplete, ...$cancelled], true);

        if ($candidates === []) {
            return ['complete' => [], 'incomplete' => [], 'cancelled' => []];
        }

        $active = $this->activeBatchIds($candidates);

        return [
            'complete' => array_values(array_filter(
                $complete,
                static fn (string $id): bool => ! isset($active[$id]),
            )),
            'incomplete' => array_values(array_filter(
                $incomplete,
                static fn (string $id): bool => ! isset($active[$id]),
            )),
            'cancelled' => array_values(array_filter(
                $cancelled,
                static fn (string $id): bool => ! isset($active[$id]),
            )),
        ];
    }

    private function hasOnlyFailedJobsPending(Batch $batch): bool
    {
        return $batch->finishedAt === null
            && $batch->failedJobs > 0
            && $batch->pendingJobs <= $batch->failedJobs;
    }

    /** @return array<int, Batch> */
    private function allBatches(): array
    {
        $batches = [];
        $cursor = null;

        while (true) {
            $page = $this->batches->get(self::BATCH_PAGE_SIZE, $cursor);

            if ($page === []) {
                break;
            }

            foreach ($page as $batch) {
                $batches[] = $batch;
            }

            $cursor = $this->advanceBatchCursor($page, $cursor);
        }

        return $batches;
    }

    /**
     * @param  array<string, true>  $candidates
     * @return array<string, true>
     */
    private function activeBatchIds(array $candidates): array
    {
        $active = [];
        $sourceTotal = max(0, (int) $this->jobs->countPending());

        for ($inspected = 0; $inspected < $sourceTotal; $inspected += self::JOB_PAGE_SIZE) {
            $rawPageSize = min(self::JOB_PAGE_SIZE, $sourceTotal - $inspected);
            $page = collect($this->jobs->getPending((string) ($inspected - 1)));

            foreach ($page->take($rawPageSize) as $job) {
                if (! is_object($job)) {
                    continue;
                }

                $batchId = $this->jobData->batchId($job);

                if ($batchId !== null && isset($candidates[$batchId])) {
                    $active[$batchId] = true;
                }
            }

            if (count($active) === count($candidates)) {
                break;
            }
        }

        return $active;
    }

    /**
     * @param  array<int, Batch>  $batches
     */
    private function advanceBatchCursor(array $batches, ?string $current): string
    {
        $batch = end($batches);

        if (! $batch instanceof Batch) {
            throw new RuntimeException('The batch repository returned an empty page.');
        }

        $cursor = $batch->id;

        if ($cursor === '' || ($current !== null && strcmp($cursor, $current) >= 0)) {
            throw new RuntimeException('The batch repository could not be scanned safely.');
        }

        return $cursor;
    }
}
