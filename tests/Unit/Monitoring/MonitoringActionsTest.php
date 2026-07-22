<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Jobs\MonitorTag as HorizonMonitorTag;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use Laravel\Horizon\Jobs\StopMonitoringTag as HorizonStopMonitoringTag;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\Monitoring\Actions\ClearRecentJobs;
use NckRtl\HorizonNewDawn\Monitoring\Actions\MonitorTag;
use NckRtl\HorizonNewDawn\Monitoring\Actions\RetryFailedJobs;
use NckRtl\HorizonNewDawn\Monitoring\Actions\StopMonitoringTag;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('dispatches supported Horizon monitor-tag jobs', function (): void {
    Bus::fake();

    (new MonitorTag(app(Dispatcher::class)))->handle('checkout');
    (new StopMonitoringTag(app(Dispatcher::class)))->handle('checkout');

    Bus::assertDispatched(
        HorizonMonitorTag::class,
        fn (HorizonMonitorTag $job): bool => $job->tag === 'checkout',
    );
    Bus::assertDispatched(
        HorizonStopMonitoringTag::class,
        fn (HorizonStopMonitoringTag $job): bool => $job->tag === 'checkout',
    );
});

it('clears recent jobs without stopping monitoring', function (): void {
    $first = array_map(static fn (int $index): string => "recent-{$index}", range(0, 49));
    $second = ['recent-50', 'recent-51'];
    $tags = mockDashboardContract(TagRepository::class);
    dashboardReturnsFor($tags, 'paginate', ['customer:42', 0, 50], $first);
    dashboardReturnsFor($tags, 'paginate', ['customer:42', 50, 50], $second);
    dashboardReturnsFor($tags, 'forget', ['customer:42'], null);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'deleteMonitored', [$first], null);
    dashboardReturnsFor($jobs, 'deleteMonitored', [$second], null);

    $cleared = (new ClearRecentJobs($tags, $jobs))->handle('customer:42');

    expect($cleared)->toBe(52);
    $tags->shouldNotHaveReceived('stopMonitoring');
});

it('retries eligible failed jobs for a monitored tag', function (): void {
    Bus::fake();

    $firstIds = array_map(static fn (int $index): string => "failed-{$index}", range(0, 49));
    $secondIds = ['failed-50', 'failed-51'];
    $firstJobRows = array_map(
        static fn (int $index): object => horizonJob($index, "failed-{$index}"),
        range(0, 49),
    );
    $retryJob = horizonJob(1, 'failed-1');
    $retryPayload = json_decode($retryJob->payload, true, flags: JSON_THROW_ON_ERROR);
    $retryJob->payload = json_encode([...$retryPayload, 'retry_of' => 'original-1'], JSON_THROW_ON_ERROR);
    $retriedJob = horizonJob(3, 'failed-3');
    $retriedJob->retried_by = json_encode([
        ['id' => 'retry-3', 'status' => 'completed', 'retried_at' => 1_784_281_100],
    ], JSON_THROW_ON_ERROR);
    $failedRetryJob = horizonJob(4, 'failed-4');
    $failedRetryJob->retried_by = json_encode([
        ['id' => 'retry-4', 'status' => 'failed', 'retried_at' => 1_784_281_100],
    ], JSON_THROW_ON_ERROR);
    $firstJobRows[1] = $retryJob;
    unset($firstJobRows[2]);
    $firstJobRows[3] = $retriedJob;
    $firstJobRows[4] = $failedRetryJob;
    $firstJobs = new Collection($firstJobRows);
    $secondJobs = new Collection([
        horizonJob(50, 'failed-50'),
    ]);

    $tags = mockDashboardContract(TagRepository::class);
    dashboardReturnsFor($tags, 'paginate', ['failed:customer:42', 0, 50], $firstIds);
    dashboardReturnsFor($tags, 'paginate', ['failed:customer:42', 50, 50], $secondIds);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'getJobs', [$firstIds], $firstJobs);
    dashboardReturnsFor($jobs, 'getJobs', [$secondIds], $secondJobs);

    $scheduled = (new RetryFailedJobs(
        $tags,
        $jobs,
        new RetryFailedJob(app(Dispatcher::class), $jobs, new FailedJobRetryEligibility),
    ))->handle('customer:42');

    expect($scheduled)->toBe(48);
    Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 48);
    Bus::assertDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
    );
    Bus::assertNotDispatched(
        HorizonRetryFailedJob::class,
        fn (HorizonRetryFailedJob $job): bool => in_array($job->id, ['failed-2', 'failed-3', 'failed-4', 'failed-51'], true),
    );
});
