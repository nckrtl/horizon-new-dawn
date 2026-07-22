<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThresholdStatus;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestampUTC(1800));

    config()->set('horizon.waits', [
        'redis:batches' => 0,
        'redis:default' => 10,
        'redis:reports' => 5,
        'sqs:reports' => 10,
    ]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('discovers supervised queues and aggregates duplicate names across connections', function (): void {
    if (queuePausingIsSupported()) {
        app(QueueManager::class)->pause('redis', 'batches');
    }

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:default,reports' => 2]],
        (object) ['processes' => ['sqs:reports' => 3, 'redis:batches' => 0]],
    ]);

    $redis = mockDashboardContract(Queue::class);
    dashboardReturnsFor($redis, 'readyNow', ['default'], 5);
    dashboardReturnsFor($redis, 'reservedSize', ['default'], 1);
    dashboardReturnsFor($redis, 'delayedSize', ['default'], 2);
    dashboardReturnsFor($redis, 'creationTimeOfOldestPendingJob', ['default'], 1740);
    dashboardReturnsFor($redis, 'readyNow', ['reports'], 7);
    dashboardReturnsFor($redis, 'reservedSize', ['reports'], 2);
    dashboardReturnsFor($redis, 'delayedSize', ['reports'], 3);
    dashboardReturnsFor($redis, 'creationTimeOfOldestPendingJob', ['reports'], 1500);
    dashboardReturnsFor($redis, 'readyNow', ['batches'], 0);
    dashboardReturnsFor($redis, 'reservedSize', ['batches'], 0);
    dashboardReturnsFor($redis, 'delayedSize', ['batches'], 0);
    dashboardReturnsFor($redis, 'creationTimeOfOldestPendingJob', ['batches'], null);

    $sqs = mockDashboardContract(Queue::class);
    dashboardReturnsFor($sqs, 'readyNow', ['reports'], 11);
    dashboardReturnsFor($sqs, 'reservedSize', ['reports'], 4);
    dashboardReturnsFor($sqs, 'delayedSize', ['reports'], 6);
    dashboardReturnsFor($sqs, 'creationTimeOfOldestPendingJob', ['reports'], 1680);

    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsFor($queues, 'connection', ['redis'], $redis);
    dashboardReturnsFor($queues, 'connection', ['sqs'], $sqs);

    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'default', 2], 4);
    dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'reports', 2], 8);
    dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['sqs', 'reports', 3], 12);
    dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'batches', 0], 0);

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturns($metrics, 'runtimeForQueue', 1000);

    $catalog = (new QueuesData(
        $supervisors,
        $queues,
        $waitTimes,
        $metrics,
        app(QueuePauseStatus::class),
        app(QueueWaitThreshold::class),
    ))->all();

    expect($catalog->toArray())->toBe([
        'available' => true,
        'queues' => [
            [
                'name' => 'batches',
                'connections' => ['redis'],
                'pauseTargets' => [
                    [
                        'connection' => 'redis',
                        'paused' => queuePausingIsSupported(),
                        'pausedUntil' => null,
                        'ready' => 0,
                        'reserved' => 0,
                        'delayed' => 0,
                        'total' => 0,
                    ],
                ],
                'ready' => 0,
                'reserved' => 0,
                'delayed' => 0,
                'processes' => 0,
                'wait' => 0,
                'waitThreshold' => [
                    'status' => 'disabled',
                    'decisiveConnection' => 'redis',
                    'waitSeconds' => 0,
                    'thresholdSeconds' => 0,
                    'oldestReadyAgeSeconds' => null,
                    'oldestReadyConnection' => null,
                    'targets' => [[
                        'connection' => 'redis',
                        'status' => 'disabled',
                        'monitored' => false,
                        'waitSeconds' => 0,
                        'thresholdSeconds' => 0,
                        'oldestReadyAgeSeconds' => null,
                    ]],
                ],
            ],
            [
                'name' => 'default',
                'connections' => ['redis'],
                'pauseTargets' => [
                    [
                        'connection' => 'redis',
                        'paused' => false,
                        'pausedUntil' => null,
                        'ready' => 5,
                        'reserved' => 1,
                        'delayed' => 2,
                        'total' => 8,
                    ],
                ],
                'ready' => 5,
                'reserved' => 1,
                'delayed' => 2,
                'processes' => 2,
                'wait' => 4,
                'waitThreshold' => [
                    'status' => 'within_bounds',
                    'decisiveConnection' => 'redis',
                    'waitSeconds' => 4,
                    'thresholdSeconds' => 10,
                    'oldestReadyAgeSeconds' => 60,
                    'oldestReadyConnection' => 'redis',
                    'targets' => [[
                        'connection' => 'redis',
                        'status' => 'within_bounds',
                        'monitored' => true,
                        'waitSeconds' => 4,
                        'thresholdSeconds' => 10,
                        'oldestReadyAgeSeconds' => 60,
                    ]],
                ],
            ],
            [
                'name' => 'reports',
                'connections' => ['redis', 'sqs'],
                'pauseTargets' => [
                    [
                        'connection' => 'redis',
                        'paused' => false,
                        'pausedUntil' => null,
                        'ready' => 7,
                        'reserved' => 2,
                        'delayed' => 3,
                        'total' => 12,
                    ],
                    [
                        'connection' => 'sqs',
                        'paused' => false,
                        'pausedUntil' => null,
                        'ready' => 11,
                        'reserved' => 4,
                        'delayed' => 6,
                        'total' => 21,
                    ],
                ],
                'ready' => 18,
                'reserved' => 6,
                'delayed' => 9,
                'processes' => 5,
                'wait' => 12,
                'waitThreshold' => [
                    'status' => 'exceeded',
                    'decisiveConnection' => 'redis',
                    'waitSeconds' => 8,
                    'thresholdSeconds' => 5,
                    'oldestReadyAgeSeconds' => 300,
                    'oldestReadyConnection' => 'redis',
                    'targets' => [
                        [
                            'connection' => 'redis',
                            'status' => 'exceeded',
                            'monitored' => true,
                            'waitSeconds' => 8,
                            'thresholdSeconds' => 5,
                            'oldestReadyAgeSeconds' => 300,
                        ],
                        [
                            'connection' => 'sqs',
                            'status' => 'exceeded',
                            'monitored' => true,
                            'waitSeconds' => 12,
                            'thresholdSeconds' => 10,
                            'oldestReadyAgeSeconds' => 120,
                        ],
                    ],
                ],
            ],
        ],
        'message' => null,
    ])->and($catalog->find('reports')?->name)->toBe('reports')
        ->and($catalog->find('missing'))->toBeNull()
        ->and($catalog->pendingCounts()->toArray())->toBe([
            'available' => true,
            'ready' => 23,
            'delayed' => 11,
        ]);
});

it('returns a safe unavailable queue catalog when Horizon storage fails', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardThrows($supervisors, 'all', new RuntimeException('redis secret'));

    $catalog = (new QueuesData(
        $supervisors,
        mockDashboardContract(QueueFactory::class),
        mockDashboardContract(WaitTimeCalculator::class),
        mockDashboardContract(MetricsRepository::class),
        app(QueuePauseStatus::class),
        app(QueueWaitThreshold::class),
    ))->all();

    expect($catalog->toArray())->toBe([
        'available' => false,
        'queues' => [],
        'message' => 'Horizon queues are currently unavailable.',
    ])->and($catalog->pendingCounts()->toArray())->toBe([
        'available' => false,
        'ready' => null,
        'delayed' => null,
    ]);
});

it('keeps the queue wait threshold available when the oldest pending timestamp cannot be read', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:default' => 1]],
    ]);

    $redis = mockDashboardContract(Queue::class);
    dashboardReturnsFor($redis, 'readyNow', ['default'], 2);
    dashboardReturnsFor($redis, 'reservedSize', ['default'], 0);
    dashboardReturnsFor($redis, 'delayedSize', ['default'], 0);
    dashboardThrowsFor(
        $redis,
        'creationTimeOfOldestPendingJob',
        ['default'],
        new RuntimeException('timestamp unavailable'),
    );

    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsFor($queues, 'connection', ['redis'], $redis);
    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'default', 1], 4);
    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturnsFor($metrics, 'runtimeForQueue', ['default'], 1000);

    $catalog = (new QueuesData(
        $supervisors,
        $queues,
        $waitTimes,
        $metrics,
        app(QueuePauseStatus::class),
        app(QueueWaitThreshold::class),
    ))->all();

    expect($catalog->available)->toBeTrue()
        ->and($catalog->queues[0]->waitThreshold->status->value)->toBe('within_bounds')
        ->and($catalog->queues[0]->waitThreshold->oldestReadyAgeSeconds)->toBeNull();
});

it('leaves the wait threshold unclassified while a queued job has no runtime data', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:default' => 1]],
    ]);

    $redis = mockDashboardContract(Queue::class);
    dashboardReturnsFor($redis, 'readyNow', ['default'], 2);
    dashboardReturnsFor($redis, 'reservedSize', ['default'], 0);
    dashboardReturnsFor($redis, 'delayedSize', ['default'], 0);
    dashboardReturnsFor($redis, 'creationTimeOfOldestPendingJob', ['default'], 1740);

    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsFor($queues, 'connection', ['redis'], $redis);

    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'default', 1], 0);

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturnsFor($metrics, 'runtimeForQueue', ['default'], 0);

    app()->instance(SupervisorRepository::class, $supervisors);
    app()->instance(QueueFactory::class, $queues);
    app()->instance(WaitTimeCalculator::class, $waitTimes);
    app()->instance(MetricsRepository::class, $metrics);

    $catalog = app(QueuesData::class)->all();

    expect($catalog->queues[0]->waitThreshold->status)->toBe(QueueWaitThresholdStatus::Calculating)
        ->and($catalog->queues[0]->waitThreshold->waitSeconds)->toBeNull();
});
