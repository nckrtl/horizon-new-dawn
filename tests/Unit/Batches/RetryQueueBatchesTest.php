<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use NckRtl\HorizonNewDawn\Batches\Actions\RetryBatch;
use NckRtl\HorizonNewDawn\Batches\Actions\RetryQueueBatches;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('continues through short non-empty repository pages until the batch scan is exhausted', function (): void {
    Bus::fake();

    $batches = mockDashboardContract(BatchRepository::class);
    $failedBatch = horizonBatch('batch-001', failedJobs: 1, failedJobIds: ['failed-1']);
    $failedBatch->options['queue'] = 'reports';
    dashboardReturnsFor($batches, 'get', [50, null], [
        horizonBatch('batch-002', failedJobs: 0),
    ]);
    dashboardReturnsFor($batches, 'get', [50, 'batch-002'], [$failedBatch]);
    dashboardReturnsFor($batches, 'get', [50, 'batch-001'], []);
    dashboardReturnsFor($batches, 'find', ['batch-001'], $failedBatch);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'getJobs', [['failed-1']], new Collection([
        horizonJob(0, 'failed-1'),
    ]));

    $data = new BatchesData($batches, new BatchJobsData($jobs, new JobsData($jobs)));
    $retry = new RetryBatch(
        $batches,
        $jobs,
        new RetryFailedJob(app(Dispatcher::class), $jobs, new FailedJobRetryEligibility),
    );

    expect((new RetryQueueBatches($batches, $data, $retry))->handle('reports'))->toBe(1);
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
    );
});

it('fails safely before retry side effects when the batch cursor does not advance', function (): void {
    Bus::fake();

    $batches = mockDashboardContract(BatchRepository::class);
    $failedBatch = horizonBatch('batch-010', failedJobs: 1, failedJobIds: ['failed-1']);
    $failedBatch->options['queue'] = 'reports';
    $invalidTail = horizonBatch('', failedJobs: 0);
    dashboardReturnsFor($batches, 'get', [50, null], [$failedBatch, $invalidTail]);

    $jobs = mockDashboardContract(JobRepository::class);
    $data = new BatchesData($batches, new BatchJobsData($jobs, new JobsData($jobs)));
    $retry = new RetryBatch(
        $batches,
        $jobs,
        new RetryFailedJob(app(Dispatcher::class), $jobs, new FailedJobRetryEligibility),
    );

    expect((new RetryQueueBatches($batches, $data, $retry))->handle('reports'))->toBe(0);
    Bus::assertNothingDispatched();
});
