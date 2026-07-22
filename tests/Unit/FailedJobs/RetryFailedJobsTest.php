<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryAllFailedJobs;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('dispatches one supported Horizon retry job', function (): void {
    Bus::fake();

    $job = horizonJob(0, 'failed-1');
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'findFailed', ['failed-1'], $job);

    $scheduled = (new RetryFailedJob(
        app(Dispatcher::class),
        $repository,
        new FailedJobRetryEligibility,
    ))->handle('failed-1');

    expect($scheduled)->toBeTrue();
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
    );
});

it('allows jobs that Horizon reports without retry metadata', function (): void {
    $job = (object) get_object_vars(horizonJob(0, 'never-retried'));
    $job->retried_by = false;

    expect((new FailedJobRetryEligibility)->allows($job))->toBeTrue();
});

it('allows individual retries after prior retries failed and blocks active or successful retries', function (): void {
    Bus::fake();

    $alreadyRetried = horizonJob(0, 'already-retried');
    $alreadyRetried->retried_by = json_encode([
        ['id' => 'failed-retry', 'status' => 'failed'],
    ], JSON_THROW_ON_ERROR);

    $neverRetried = horizonJob(0, 'never-retried');
    $neverRetried->retried_by = '[]';

    $retryChild = horizonJob(1, 'retry-child');
    $retryPayload = json_decode($retryChild->payload, true, flags: JSON_THROW_ON_ERROR);
    $retryChild->payload = json_encode([...$retryPayload, 'retry_of' => 'original'], JSON_THROW_ON_ERROR);

    $completed = horizonJob(2, 'completed-retry');
    $completed->retried_by = json_encode([
        ['id' => 'completed-child', 'status' => 'completed'],
    ], JSON_THROW_ON_ERROR);

    $pending = horizonJob(3, 'pending-retry');
    $pending->retried_by = json_encode([
        ['id' => 'pending-child', 'status' => 'pending'],
    ], JSON_THROW_ON_ERROR);

    $unknown = horizonJob(4, 'unknown-retry');
    $unknown->retried_by = json_encode([
        ['id' => 'unknown-child', 'status' => 'reserved'],
    ], JSON_THROW_ON_ERROR);

    $malformed = horizonJob(5, 'malformed-retries');
    $malformed->retried_by = '{not-json';

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'findFailed', ['already-retried'], $alreadyRetried);
    dashboardReturnsFor($repository, 'findFailed', ['never-retried'], $neverRetried);
    dashboardReturnsFor($repository, 'findFailed', ['retry-child'], $retryChild);
    dashboardReturnsFor($repository, 'findFailed', ['completed-retry'], $completed);
    dashboardReturnsFor($repository, 'findFailed', ['pending-retry'], $pending);
    dashboardReturnsFor($repository, 'findFailed', ['unknown-retry'], $unknown);
    dashboardReturnsFor($repository, 'findFailed', ['malformed-retries'], $malformed);
    dashboardReturnsFor($repository, 'findFailed', ['missing'], null);

    $action = new RetryFailedJob(
        app(Dispatcher::class),
        $repository,
        new FailedJobRetryEligibility,
    );

    expect($action->handle('already-retried'))->toBeTrue()
        ->and($action->handle('never-retried'))->toBeTrue()
        ->and($action->handle('retry-child'))->toBeTrue()
        ->and($action->handle('completed-retry'))->toBeFalse()
        ->and($action->handle('pending-retry'))->toBeFalse()
        ->and($action->handle('unknown-retry'))->toBeFalse()
        ->and($action->handle('malformed-retries'))->toBeFalse()
        ->and($action->handle('missing'))->toBeFalse();

    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 3);
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'already-retried',
    );
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'retry-child',
    );
});

it('walks failed chunks deduplicates ids and includes retry leaves', function (): void {
    Bus::fake();

    $first = new Collection(array_map(
        function (int $index): object {
            $job = horizonJob($index, "failed-{$index}");

            if ($index === 1) {
                $payload = json_decode((string) $job->payload, true, flags: JSON_THROW_ON_ERROR);
                $job->payload = json_encode([...$payload, 'retry_of' => 'original-1'], JSON_THROW_ON_ERROR);
            }

            return $job;
        },
        range(0, 49),
    ));
    $second = new Collection([
        horizonJob(50, 'failed-50'),
        horizonJob(51, 'failed-2'),
    ]);

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'getFailed', ['-1'], $first);
    dashboardReturnsFor($repository, 'getFailed', ['49'], $second);

    $action = new RetryAllFailedJobs(
        $repository,
        new RetryFailedJob(app(Dispatcher::class), $repository, new FailedJobRetryEligibility),
    );

    expect($action->handle())->toBe(51);
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 51);
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
    );
});

it('retries only failures from the requested connection and queue', function (): void {
    Bus::fake();

    $matching = horizonJob(0, 'failed-batches');
    $matching->queue = 'batches';

    $otherQueue = horizonJob(1, 'failed-reports');
    $otherQueue->queue = 'reports';

    $otherConnection = horizonJob(2, 'failed-sqs-batches');
    $otherConnection->connection = 'sqs';
    $otherConnection->queue = 'batches';

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturns($repository, 'getFailed', new Collection([
        $matching,
        $otherQueue,
        $otherConnection,
    ]));

    $action = new RetryAllFailedJobs(
        $repository,
        new RetryFailedJob(app(Dispatcher::class), $repository, new FailedJobRetryEligibility),
    );

    expect($action->handle('redis', 'batches'))->toBe(1);
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-batches',
    );
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 1);
});

it('stops retry-all when a full chunk does not advance', function (): void {
    Bus::fake();

    $first = new Collection(array_map(
        fn (int $index): object => horizonJob(0, "failed-{$index}"),
        range(0, 49),
    ));
    $second = new Collection(array_map(
        fn (int $index): object => horizonJob(0, "next-failed-{$index}"),
        range(0, 49),
    ));

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'getFailed', ['-1'], $first);
    dashboardReturnsFor($repository, 'getFailed', ['0'], $second);

    $action = new RetryAllFailedJobs(
        $repository,
        new RetryFailedJob(app(Dispatcher::class), $repository, new FailedJobRetryEligibility),
    );

    expect($action->handle())->toBe(100);
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 100);
});

it('bulk retries the failed leaf instead of branching again from its parent', function (): void {
    Bus::fake();

    $parent = horizonJob(0, 'parent');
    $parent->retried_by = json_encode([
        ['id' => 'retry-leaf', 'status' => 'failed'],
    ], JSON_THROW_ON_ERROR);
    $retryLeaf = horizonJob(1, 'retry-leaf');
    $payload = json_decode($retryLeaf->payload, true, flags: JSON_THROW_ON_ERROR);
    $retryLeaf->payload = json_encode([...$payload, 'retry_of' => 'parent'], JSON_THROW_ON_ERROR);

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturns($repository, 'getFailed', new Collection([$parent, $retryLeaf]));

    $scheduled = (new RetryAllFailedJobs(
        $repository,
        new RetryFailedJob(app(Dispatcher::class), $repository, new FailedJobRetryEligibility),
    ))->handle();

    expect($scheduled)->toBe(1);
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 1);
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'retry-leaf',
    );
});
