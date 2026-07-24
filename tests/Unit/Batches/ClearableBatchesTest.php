<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Batches\BatchClearScope;
use NckRtl\HorizonNewDawn\Batches\ClearableBatches;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

function clearableBatches(BatchRepository $batches, JobRepository $jobs): ClearableBatches
{
    return new ClearableBatches(
        $batches,
        $jobs,
        new JobsData($jobs),
    );
}

it('keeps later active retry batches out of clearable ids despite stale earlier pending hashes', function (): void {
    $batches = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batches, 'get', [1000, null], [
        horizonBatch('batch-2', pendingJobs: 1, failedJobs: 1),
        horizonBatch('batch-1', pendingJobs: 1, failedJobs: 1),
    ]);
    dashboardReturnsFor($batches, 'get', [1000, 'batch-1'], []);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'countPending', [], 51);
    dashboardReturnsFor($jobs, 'getPending', ['-1'], new Collection);
    $active = horizonJob(50, 'pending-50');
    $payload = json_decode($active->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['data']['batchId'] = 'batch-1';
    $active->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    dashboardReturnsFor($jobs, 'getPending', ['49'], new Collection([$active]));

    $ids = clearableBatches($batches, $jobs)->ids(BatchClearScope::Incomplete);

    expect($ids)->toBe(['batch-2']);
});
