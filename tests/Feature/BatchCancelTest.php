<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Horizon\Horizon;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('cancels an active batch through the Laravel batch contract', function (): void {
    $batches = mockDashboardContract(BatchRepository::class);
    $batch = horizonBatch('batch-1', pendingJobs: 4, repository: $batches);
    dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
    dashboardReturnsFor($batches, 'cancel', ['batch-1'], null);
    app()->instance(BatchRepository::class, $batches);

    post('/horizon/batches/batch-1/cancel')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Batch cancelled.');
});

it('rejects a batch without cancellable pending jobs', function (): void {
    $batches = mockDashboardContract(BatchRepository::class);
    $batch = horizonBatch('batch-1', pendingJobs: 2, failedJobs: 2, repository: $batches);
    dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
    app()->instance(BatchRepository::class, $batches);

    post('/horizon/batches/batch-1/cancel')
        ->assertRedirect()
        ->assertSessionHas('toast.error', 'This batch can no longer be cancelled.');
});

it('rejects an already cancelled batch', function (): void {
    $batches = mockDashboardContract(BatchRepository::class);
    $batch = horizonBatch(
        'batch-1',
        pendingJobs: 4,
        cancelledAt: 1_784_281_050,
        repository: $batches,
    );
    dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
    app()->instance(BatchRepository::class, $batches);

    post('/horizon/batches/batch-1/cancel')
        ->assertRedirect()
        ->assertSessionHas('toast.error', 'This batch can no longer be cancelled.');
});

it('honors Horizon authorization when cancelling a batch', function (): void {
    Horizon::auth(static fn (): bool => false);

    post('/horizon/batches/batch-1/cancel')->assertForbidden();
});
