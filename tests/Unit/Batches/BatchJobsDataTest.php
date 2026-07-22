<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Tests\Support\HorizonJob;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('BatchJobsData', function (): void {
    it('returns authoritative retained pending completed and failed batch jobs', function (): void {
        $batch = horizonBatch(
            'batch-42',
            totalJobs: 5,
            pendingJobs: 2,
            failedJobs: 1,
            failedJobIds: ['failed-1'],
        );
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getPending', [null], collect([
            retainedBatchJob(0, 'pending-1', 'batch-42', 'pending'),
            retainedBatchJob(1, 'other-pending', 'other-batch', 'pending'),
        ]));
        dashboardReturnsFor($repository, 'getCompleted', [null], collect([
            retainedBatchJob(0, 'completed-1', 'batch-42', 'completed'),
            retainedBatchJob(1, 'completed-2', 'batch-42', 'completed'),
            retainedBatchJob(2, 'completed-3', 'batch-42', 'completed'),
        ]));
        dashboardReturnsFor($repository, 'getJobs', [['failed-1']], collect([
            retainedBatchJob(0, 'failed-1', 'batch-42', 'failed'),
        ]));

        $lists = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch);

        expect($lists->pending->total)->toBe(1)
            ->and($lists->pending->rows)->toHaveCount(1)
            ->and($lists->pending->rows[0]->id)->toBe('pending-1')
            ->and($lists->completed->total)->toBe(3)
            ->and($lists->completed->rows)->toHaveCount(3)
            ->and($lists->failed->total)->toBe(1)
            ->and($lists->failed->rows)->toHaveCount(1)
            ->and($lists->pending->complete)->toBeTrue()
            ->and($lists->completed->complete)->toBeTrue()
            ->and($lists->failed->complete)->toBeTrue();
    });

    it('does not count failed batch jobs as pending jobs', function (): void {
        $batch = horizonBatch(
            'batch-42',
            totalJobs: 4,
            pendingJobs: 1,
            failedJobs: 1,
            failedJobIds: ['failed-1'],
        );
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getCompleted', [null], collect([
            retainedBatchJob(0, 'completed-1', 'batch-42', 'completed'),
            retainedBatchJob(1, 'completed-2', 'batch-42', 'completed'),
            retainedBatchJob(2, 'completed-3', 'batch-42', 'completed'),
        ]));
        dashboardReturnsFor($repository, 'getJobs', [['failed-1']], collect([
            retainedBatchJob(3, 'failed-1', 'batch-42', 'failed'),
        ]));

        $lists = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch);

        expect($lists->pending->total)->toBe(0)
            ->and($lists->pending->rows)->toBe([])
            ->and($lists->completed->total)->toBe(3)
            ->and($lists->completed->rows)->toHaveCount(3)
            ->and($lists->failed->total)->toBe(1);
    });

    it('does not query Horizon for zero-count job lists', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch('empty', totalJobs: 0, pendingJobs: 0);

        $lists = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch);

        expect($lists->pending->rows)->toBe([])
            ->and($lists->completed->rows)->toBe([])
            ->and($lists->failed->rows)->toBe([])
            ->and($lists->pending->complete)->toBeTrue()
            ->and($lists->completed->complete)->toBeTrue()
            ->and($lists->failed->complete)->toBeTrue();
    });

    it('marks a short retained page with missing matches as incomplete', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch('batch-42', totalJobs: 2, pendingJobs: 2);
        dashboardReturnsFor($repository, 'getPending', [null], collect([
            retainedBatchJob(0, 'pending-1', 'batch-42', 'pending'),
            retainedBatchJob(1, 'other-pending', 'other-batch', 'pending'),
        ]));

        $pending = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->pending;

        expect($pending->available)->toBeTrue()
            ->and($pending->complete)->toBeFalse()
            ->and($pending->rows)->toHaveCount(1)
            ->and($pending->message)->toBe('Some pending jobs are no longer retained by Horizon.');
    });

    it('advances a full retained page using its final Horizon index', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch('batch-42', totalJobs: 1, pendingJobs: 1);
        dashboardReturnsFor($repository, 'getPending', [null], collect(array_map(
            static fn (int $index): HorizonJob => retainedBatchJob($index, "other-{$index}", 'other-batch', 'pending'),
            range(0, 49),
        )));
        dashboardReturnsFor($repository, 'getPending', ['49'], collect([
            retainedBatchJob(50, 'pending-1', 'batch-42', 'pending'),
        ]));

        $pending = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->pending;

        expect($pending->complete)->toBeTrue()
            ->and($pending->rows)->toHaveCount(1)
            ->and($pending->rows[0]->id)->toBe('pending-1');
    });

    it('stops scanning after 250 retained jobs', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch('batch-42', totalJobs: 1, pendingJobs: 1);

        foreach ([null, '49', '99', '149', '199'] as $page => $cursor) {
            $start = $page * 50;
            dashboardReturnsFor($repository, 'getPending', [$cursor], collect(array_map(
                static fn (int $index): HorizonJob => retainedBatchJob($index, "other-{$index}", 'other-batch', 'pending'),
                range($start, $start + 49),
            )));
        }

        $pending = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->pending;

        expect($pending->available)->toBeTrue()
            ->and($pending->complete)->toBeFalse()
            ->and($pending->rows)->toBe([]);
    });

    it('stops safely when Horizon returns a non-advancing cursor', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch('batch-42', totalJobs: 1, pendingJobs: 1);
        $page = collect(array_map(
            static fn (int $index): HorizonJob => retainedBatchJob($index, "other-{$index}", 'other-batch', 'pending'),
            range(0, 49),
        ));
        dashboardReturnsFor($repository, 'getPending', [null], $page);
        dashboardReturnsFor($repository, 'getPending', ['49'], $page);

        $pending = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->pending;

        expect($pending->available)->toBeTrue()
            ->and($pending->complete)->toBeFalse()
            ->and($pending->rows)->toBe([]);
    });

    it('isolates a completed repository failure from the other job lists', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch('batch-42', totalJobs: 2, pendingJobs: 0);
        dashboardThrows($repository, 'getCompleted', new RuntimeException('redis password leaked'));

        $lists = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch);

        expect($lists->pending->available)->toBeTrue()
            ->and($lists->failed->available)->toBeTrue()
            ->and($lists->completed->available)->toBeFalse()
            ->and($lists->completed->complete)->toBeFalse()
            ->and($lists->completed->message)->toBe('Completed jobs for this batch are currently unavailable.')
            ->and($lists->completed->message)->not->toContain('password');
    });

    it('marks missing failed hashes as incomplete instead of unavailable', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch(
            'batch-42',
            totalJobs: 1,
            pendingJobs: 1,
            failedJobs: 1,
            failedJobIds: ['failed-1'],
        );
        dashboardReturnsFor($repository, 'getJobs', [['failed-1']], new Collection);

        $failed = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->failed;

        expect($failed->available)->toBeTrue()
            ->and($failed->complete)->toBeFalse()
            ->and($failed->rows)->toBe([])
            ->and($failed->message)->toBe('Some failed jobs are no longer retained by Horizon.');
    });

    it('collapses retained retry chains into logical failed jobs and totals their attempts', function (): void {
        $batch = horizonBatch(
            'batch-42',
            totalJobs: 4,
            pendingJobs: 2,
            failedJobs: 4,
            failedJobIds: ['original-1', 'retry-1', 'retry-2', 'trimmed-lineage'],
        );
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getJobs', [$batch->failedJobIds], collect([
            retainedBatchRetry(0, 'original-1', null, 2, 'retry-1'),
            retainedBatchRetry(1, 'retry-1', 'original-1', 1, 'retry-2'),
            retainedBatchRetry(2, 'retry-2', 'retry-1', 3),
        ]));

        $failed = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->failed;

        expect($failed->total)->toBe(2)
            ->and($failed->rows)->toHaveCount(1)
            ->and($failed->rows[0]->id)->toBe('retry-2')
            ->and($failed->rows[0]->attempts)->toBe(6)
            ->and($failed->rows[0]->attemptsComplete)->toBeTrue()
            ->and($failed->complete)->toBeFalse()
            ->and($failed->message)->toBe('Some failed jobs are no longer retained by Horizon.');
    });

    it('reports a lower-bound attempt total when the start of a retry chain was trimmed', function (): void {
        $batch = horizonBatch(
            'batch-42',
            totalJobs: 1,
            pendingJobs: 1,
            failedJobs: 2,
            failedJobIds: ['trimmed-parent', 'retry-1'],
        );
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getJobs', [$batch->failedJobIds], collect([
            retainedBatchRetry(1, 'retry-1', 'trimmed-parent', 2),
        ]));

        $failed = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->failed;

        expect($failed->total)->toBe(1)
            ->and($failed->rows)->toHaveCount(1)
            ->and($failed->rows[0]->attempts)->toBe(3)
            ->and($failed->rows[0]->attemptsComplete)->toBeFalse()
            ->and($failed->complete)->toBeFalse();
    });

    it('does not count a queued retry as an unknown execution attempt', function (): void {
        $batch = horizonBatch(
            'batch-42',
            totalJobs: 1,
            pendingJobs: 1,
            failedJobs: 1,
            failedJobIds: ['original-1'],
        );
        $original = retainedBatchRetry(0, 'original-1', null, 2);
        $original->retried_by = json_encode([
            ['id' => 'pending-retry', 'status' => 'pending'],
        ], JSON_THROW_ON_ERROR);
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getJobs', [$batch->failedJobIds], collect([$original]));

        $failed = (new BatchJobsData($repository, new JobsData($repository)))->forBatch($batch)->failed;

        expect($failed->rows)->toHaveCount(1)
            ->and($failed->rows[0]->attempts)->toBe(2)
            ->and($failed->rows[0]->attemptsComplete)->toBeTrue()
            ->and($failed->complete)->toBeTrue();
    });
});

function retainedBatchJob(
    int $index,
    string $id,
    string $batchId,
    string $status,
): HorizonJob {
    $job = horizonJob($index, $id);
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['data']['batchId'] = $batchId;
    $job->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    $job->status = $status;

    if ($status === 'pending') {
        $job->completed_at = null;
    }

    if ($status === 'failed') {
        $job->completed_at = null;
        $job->failed_at = '1784281004.25';
    }

    return $job;
}

function retainedBatchRetry(
    int $index,
    string $id,
    ?string $retryOf,
    int $attempts,
    ?string $retriedBy = null,
): HorizonJob {
    $job = retainedBatchJob($index, $id, 'batch-42', 'failed');
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['attempts'] = $attempts;

    if ($retryOf !== null) {
        $payload['retry_of'] = $retryOf;
    }

    $job->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    $job->retried_by = $retriedBy === null
        ? null
        : json_encode([
            ['id' => $retriedBy, 'status' => 'failed'],
        ], JSON_THROW_ON_ERROR);

    return $job;
}
