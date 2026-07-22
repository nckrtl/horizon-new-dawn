<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('clears only the failed jobs belonging to the selected batch', function (): void {
    $batch = horizonBatch(
        'batch-1',
        failedJobs: 3,
        failedJobIds: ['failed-1', 'failed-2', 'failed-1', '', '   '],
    );
    $batches = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
    app()->instance(BatchRepository::class, $batches);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'deleteFailed', ['failed-1'], 1);
    dashboardReturnsFor($jobs, 'deleteFailed', ['failed-2'], 1);
    app()->instance(JobRepository::class, $jobs);

    $failedJobs = mockDashboardContract(FailedJobProviderInterface::class);
    dashboardReturnsFor($failedJobs, 'forget', ['failed-1'], true);
    dashboardReturnsFor($failedJobs, 'forget', ['failed-2'], true);
    app()->instance(FailedJobProviderInterface::class, $failedJobs);

    delete('/horizon/batches/batch-1/failed')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 2 failed batch jobs.');
});

it('returns zero when the batch no longer exists', function (): void {
    $batches = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batches, 'find', ['missing'], null);
    app()->instance(BatchRepository::class, $batches);
    app()->instance(JobRepository::class, mockDashboardContract(JobRepository::class));

    delete('/horizon/batches/missing/failed')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 0 failed batch jobs.');
});

it('honors Horizon authorization when clearing batch failures', function (): void {
    Horizon::auth(static fn (): bool => false);

    delete('/horizon/batches/batch-1/failed')->assertForbidden();
});
