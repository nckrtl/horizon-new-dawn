<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
    config()->set('queue.connections.redis', ['driver' => 'redis']);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('retries failed jobs from one queue', function (): void {
    Bus::fake();

    $matching = horizonJob(0, 'failed-batches');
    $matching->queue = 'batches';

    $other = horizonJob(1, 'failed-reports');
    $other->queue = 'reports';

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturns($repository, 'getFailed', new Collection([$matching, $other]));
    app()->instance(JobRepository::class, $repository);

    post('/horizon/queues/redis/batches/retry-failed')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Scheduled 1 failed job from batches for retry.');

    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-batches',
    );
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 1);
});
