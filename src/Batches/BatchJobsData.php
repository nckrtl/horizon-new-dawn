<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use LogicException;
use NckRtl\HorizonNewDawn\Batches\Data\BatchJobListData;
use NckRtl\HorizonNewDawn\Batches\Data\BatchJobListsData;
use NckRtl\HorizonNewDawn\Jobs\Data\JobRowData;
use NckRtl\HorizonNewDawn\Jobs\JobListType;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use Throwable;

final readonly class BatchJobsData
{
    private const int PAGE_SIZE = 50;

    private const int SCAN_LIMIT = 250;

    public function __construct(
        private JobRepository $jobs,
        private JobsData $jobData,
    ) {}

    public function forBatch(Batch $batch): BatchJobListsData
    {
        $pending = max(0, $batch->pendingJobs - $batch->failedJobs);
        $completed = max(0, $batch->processedJobs());

        return new BatchJobListsData(
            pending: $this->retained($batch->id, $pending, JobListType::Pending),
            completed: $this->retained($batch->id, $completed, JobListType::Completed),
            failed: $this->failed($batch),
        );
    }

    private function retained(string $batchId, int $total, JobListType $type): BatchJobListData
    {
        if ($total <= 0) {
            return $this->empty($total);
        }

        try {
            $rows = [];
            $cursor = null;
            $visitedCursors = [];
            $inspected = 0;

            while ($inspected < self::SCAN_LIMIT && count($rows) < $total) {
                $page = match ($type) {
                    JobListType::Pending => $this->jobs->getPending($cursor),
                    JobListType::Completed => $this->jobs->getCompleted($cursor),
                    JobListType::Silenced => throw new LogicException('Batch job history does not scan silenced jobs.'),
                };
                $page = collect($page);

                foreach ($page as $job) {
                    if ($inspected >= self::SCAN_LIMIT || count($rows) >= $total) {
                        break;
                    }

                    $inspected++;

                    if (! is_object($job) || $this->jobData->batchId($job) !== $batchId) {
                        continue;
                    }

                    $row = $this->jobData->row($job);

                    if ($row !== null) {
                        $rows[] = $row;
                    }
                }

                if (count($rows) >= $total || $page->count() < self::PAGE_SIZE) {
                    break;
                }

                $next = $this->lastIndex($page);

                if ($next === null || $next === $cursor || isset($visitedCursors[$next])) {
                    break;
                }

                $visitedCursors[$next] = true;
                $cursor = $next;
            }

            return $this->result(
                total: $total,
                rows: $rows,
                incompleteMessage: "Some {$type->value} jobs are no longer retained by Horizon.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return new BatchJobListData(
                total: $total,
                rows: [],
                available: false,
                complete: false,
                message: ucfirst($type->value).' jobs for this batch are currently unavailable.',
            );
        }
    }

    private function failed(Batch $batch): BatchJobListData
    {
        $total = max(0, $batch->failedJobs);

        if ($total === 0) {
            return $this->empty(0);
        }

        $ids = array_values(array_filter($batch->failedJobIds, is_string(...)));

        if ($ids === []) {
            return $this->result(
                total: $total,
                rows: [],
                incompleteMessage: 'Some failed jobs are no longer retained by Horizon.',
            );
        }

        try {
            $rows = [];

            foreach ($this->jobs->getJobs($ids) as $job) {
                if (count($rows) >= $total) {
                    break;
                }

                if (! is_object($job)) {
                    continue;
                }

                $row = $this->jobData->row($job);

                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            return $this->result(
                total: $total,
                rows: $rows,
                incompleteMessage: 'Some failed jobs are no longer retained by Horizon.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return new BatchJobListData(
                total: $total,
                rows: [],
                available: false,
                complete: false,
                message: 'Failed jobs for this batch are currently unavailable.',
            );
        }
    }

    private function empty(int $total): BatchJobListData
    {
        return new BatchJobListData(
            total: max(0, $total),
            rows: [],
            available: true,
            complete: true,
            message: null,
        );
    }

    /**
     * @param  array<int, JobRowData>  $rows
     */
    private function result(int $total, array $rows, string $incompleteMessage): BatchJobListData
    {
        $complete = count($rows) >= $total;

        return new BatchJobListData(
            total: $total,
            rows: $rows,
            available: true,
            complete: $complete,
            message: $complete ? null : $incompleteMessage,
        );
    }

    /** @param Collection<int, mixed> $jobs */
    private function lastIndex(Collection $jobs): ?string
    {
        $last = $jobs->last();

        return is_object($last) && is_numeric($last->index ?? null)
            ? (string) $last->index
            : null;
    }
}
