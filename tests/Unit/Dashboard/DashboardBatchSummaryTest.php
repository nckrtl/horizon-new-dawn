<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use NckRtl\HorizonNewDawn\Dashboard\DashboardBatchSummary;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('counts and hydrates only genuinely active batches', function (): void {
    config()->set('queue.batching.database', null);
    config()->set('queue.batching.table', 'job_batches');

    $countBuilder = mockDashboardContract(Builder::class);
    dashboardReturnsFor($countBuilder, 'whereNull', ['cancelled_at'], $countBuilder);
    dashboardReturnsFor($countBuilder, 'whereColumn', ['pending_jobs', '>', 'failed_jobs'], $countBuilder);
    dashboardReturnsFor($countBuilder, 'count', [], 4);

    $previewBuilder = mockDashboardContract(Builder::class);
    dashboardReturnsFor($previewBuilder, 'whereNull', ['cancelled_at'], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'whereColumn', ['pending_jobs', '>', 'failed_jobs'], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'orderByDesc', ['id'], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'limit', [3], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'pluck', ['id'], collect(['batch-4', 'batch-3', 'batch-2']));

    $connection = mockDashboardContract(ConnectionInterface::class);
    dashboardReturnsFor($connection, 'table', ['job_batches'], $countBuilder);
    dashboardReturnsFor($connection, 'table', ['job_batches'], $previewBuilder);
    $database = mockDashboardContract(ConnectionResolverInterface::class);
    dashboardReturnsFor($database, 'connection', [null], $connection);
    dashboardReturnsFor($database, 'connection', [null], $connection);

    $batches = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batches, 'find', ['batch-4'], horizonBatch('batch-4', name: 'Newest', totalJobs: 4, pendingJobs: 2));
    dashboardReturnsFor($batches, 'find', ['batch-3'], horizonBatch('batch-3', name: '', totalJobs: 4, pendingJobs: 1));
    dashboardReturnsFor($batches, 'find', ['batch-2'], horizonBatch('batch-2', name: 'Older', totalJobs: 4, pendingJobs: 3));

    expect((new DashboardBatchSummary($batches, $database))->get()->toArray())->toBe([
        'active' => 4,
        'previews' => [
            ['id' => 'batch-4', 'name' => 'Newest', 'progress' => 50],
            ['id' => 'batch-3', 'name' => 'batch-3', 'progress' => 75],
            ['id' => 'batch-2', 'name' => 'Older', 'progress' => 25],
        ],
    ]);
});
