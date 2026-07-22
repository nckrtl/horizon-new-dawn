<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use NckRtl\HorizonNewDawn\Batches\Actions\RetryBatch;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('RetryBatch', function (): void {
    it('retries eligible failed leaves once', function (): void {
        Bus::fake();

        $batch = horizonBatch('batch-1', failedJobs: 3, failedJobIds: ['failed-1', 'failed-2', 'failed-1']);
        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
        $retry = horizonJob(0, 'failed-2');
        $retry->payload = json_encode(['retry_of' => 'original-2'], JSON_THROW_ON_ERROR);
        $alreadyCompleted = horizonJob(0, 'failed-3');
        $alreadyCompleted->retried_by = json_encode([
            ['id' => 'retry-3', 'status' => 'completed'],
        ], JSON_THROW_ON_ERROR);
        $allRetriesFailed = horizonJob(0, 'failed-4');
        $allRetriesFailed->retried_by = json_encode([
            ['id' => 'retry-4', 'status' => 'failed'],
        ], JSON_THROW_ON_ERROR);
        $jobs = mockDashboardContract(JobRepository::class);
        $batch->failedJobIds = ['failed-1', 'failed-2', 'failed-1', 'failed-3', 'failed-4'];
        dashboardReturnsFor($jobs, 'getJobs', [$batch->failedJobIds], new Collection([
            horizonJob(0, 'failed-1'),
            $retry,
            horizonJob(0, 'failed-1'),
            $alreadyCompleted,
            $allRetriesFailed,
        ]));

        $action = new RetryBatch(
            $batches,
            $jobs,
            new RetryFailedJob(app(Dispatcher::class), $jobs, new FailedJobRetryEligibility),
        );

        expect($action->handle('batch-1'))->toBe(2);
        Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 2);
        Bus::assertDispatched(
            HorizonRetryFailedJob::class,
            fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
        );
        Bus::assertDispatched(
            HorizonRetryFailedJob::class,
            fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-2',
        );
        Bus::assertNotDispatched(
            HorizonRetryFailedJob::class,
            fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-4',
        );
    });

    it('does nothing when the batch no longer exists', function (): void {
        Bus::fake();

        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'find', ['missing'], null);
        $jobs = mockDashboardContract(JobRepository::class);
        $action = new RetryBatch(
            $batches,
            $jobs,
            new RetryFailedJob(app(Dispatcher::class), $jobs, new FailedJobRetryEligibility),
        );

        expect($action->handle('missing'))->toBe(0);
        Bus::assertNothingDispatched();
    });
});
