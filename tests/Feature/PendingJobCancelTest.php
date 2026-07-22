<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;

use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserPageFixtures;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
    bindBrowserPageFixtures();
});

it('cancels an individual pending job', function (): void {
    delete('/horizon/jobs/pending/pending-1')
        ->assertSessionHas('toast.success', 'Job cancelled.')
        ->assertRedirect('/horizon/jobs/pending');
});

it('cancels all pending jobs in the requested state', function (): void {
    $job = horizonJob(0, 'pending-1');
    $job->status = 'pending';
    $job->completed_at = null;

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countPending', 1);
    dashboardReturnsFor($jobs, 'getPending', ['-1'], new Collection([$job]));
    dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));
    app()->instance(JobRepository::class, $jobs);

    delete('/horizon/jobs/pending/cancel/delayed')
        ->assertSessionHas('toast.success', 'Cancelled 1 delayed job.')
        ->assertRedirect();
});

it('rejects unsupported pending cancellation scopes', function (): void {
    delete('/horizon/jobs/pending/cancel/reserved')->assertStatus(405);
});
