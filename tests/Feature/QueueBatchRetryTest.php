<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
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

it('retries failed jobs only from retained batches for the selected queue', function (): void {
    Bus::fake();

    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.queue', 'reports');

    $matching = horizonBatch('matching', failedJobs: 1, failedJobIds: ['failed-matching']);
    $matching->options = [];
    $otherQueue = horizonBatch('other', failedJobs: 1, failedJobIds: ['failed-other']);
    $otherQueue->options['queue'] = 'other';
    $withoutFailures = horizonBatch('successful', failedJobs: 0);
    $withoutFailures->options['queue'] = 'reports';

    $batches = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batches, 'get', [50, null], [$matching, $otherQueue, $withoutFailures]);
    dashboardReturnsFor($batches, 'find', ['matching'], $matching);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'getJobs', [['failed-matching']], new Collection([
        horizonJob(0, 'failed-matching'),
    ]));

    app()->instance(BatchRepository::class, $batches);
    app()->instance(JobRepository::class, $jobs);

    post('/horizon/queues/reports/batches/retry-failed-jobs')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Scheduled 1 failed batch job from reports for retry.');

    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-matching',
    );
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 1);
});
