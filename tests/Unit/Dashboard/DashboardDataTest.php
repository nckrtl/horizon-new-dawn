<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Dashboard\DashboardBatchSummary;
use NckRtl\HorizonNewDawn\Dashboard\DashboardData;
use NckRtl\HorizonNewDawn\Dashboard\DashboardPendingState;
use NckRtl\HorizonNewDawn\Dashboard\HorizonStatus;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    MasterSupervisor::$nameResolver = null;
});

/** @param array<int, object> $masters */
function summaryDashboardData(array $masters): DashboardData
{
    CarbonImmutable::setTestNow('2026-07-18 12:00:00 UTC');
    config()->set('queue.batching.database', null);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countFailed', 23);
    dashboardReturns($jobs, 'countCompleted', 36);
    dashboardReturns($jobs, 'countPending', 5);

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturns($metrics, 'queueWithMaximumRuntime', 'exports');
    dashboardReturns($metrics, 'queueWithMaximumThroughput', 'default');

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:default' => 4, 'redis:mail' => 2]],
    ]);

    $masterSupervisors = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masterSupervisors, 'all', $masters);

    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturns($waitTimes, 'calculate', ['redis:default' => 2]);

    $queue = mockDashboardContract(Queue::class);
    dashboardReturnsFor($queue, 'reservedSize', ['default'], 3);
    dashboardReturnsFor($queue, 'readyNow', ['default'], 8);
    dashboardReturnsFor($queue, 'delayedSize', ['default'], 11);
    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsFor($queues, 'connection', ['redis'], $queue);

    $countBuilder = mockDashboardContract(Builder::class);
    dashboardReturnsFor($countBuilder, 'whereNull', ['cancelled_at'], $countBuilder);
    dashboardReturnsFor($countBuilder, 'whereColumn', ['pending_jobs', '>', 'failed_jobs'], $countBuilder);
    dashboardReturnsFor($countBuilder, 'count', [], 4);
    $previewBuilder = mockDashboardContract(Builder::class);
    dashboardReturnsFor($previewBuilder, 'whereNull', ['cancelled_at'], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'whereColumn', ['pending_jobs', '>', 'failed_jobs'], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'orderByDesc', ['id'], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'limit', [3], $previewBuilder);
    dashboardReturnsFor($previewBuilder, 'pluck', ['id'], collect(['batch-3', 'batch-2', 'batch-1']));
    $databaseConnection = mockDashboardContract(ConnectionInterface::class);
    dashboardReturnsFor($databaseConnection, 'table', ['job_batches'], $countBuilder);
    dashboardReturnsFor($databaseConnection, 'table', ['job_batches'], $previewBuilder);
    $database = mockDashboardContract(ConnectionResolverInterface::class);
    dashboardReturnsFor($database, 'connection', [null], $databaseConnection);
    dashboardReturnsFor($database, 'connection', [null], $databaseConnection);
    $batches = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batches, 'find', ['batch-3'], horizonBatch('batch-3', name: 'Archive audit logs', totalJobs: 100, pendingJobs: 52));
    dashboardReturnsFor($batches, 'find', ['batch-2'], horizonBatch('batch-2', name: 'Import order CSV', totalJobs: 100, pendingJobs: 87));
    dashboardReturnsFor($batches, 'find', ['batch-1'], horizonBatch('batch-1', name: 'Reindex product catalog', totalJobs: 100, pendingJobs: 28));

    $connection = mockDashboardContract(Connection::class);
    $failedRetentionMinutes = max(0, (int) config('horizon.trim.failed', 10080));
    $completedRetentionMinutes = max(0, (int) config('horizon.trim.completed', 60));
    $failedHourCutoff = CarbonImmutable::now()->subMinutes(min(60, $failedRetentionMinutes));
    $failedDayCutoff = CarbonImmutable::now()->subMinutes(min(1440, $failedRetentionMinutes));
    $completedHourCutoff = CarbonImmutable::now()->subMinutes(min(60, $completedRetentionMinutes));
    $completedDayCutoff = CarbonImmutable::now()->subMinutes(min(1440, $completedRetentionMinutes));
    dashboardReturnsFor(
        $connection,
        'zcount',
        ['failed_jobs', '-inf', (string) ($failedHourCutoff->getTimestamp() * -1)],
        2,
    );
    dashboardReturnsFor(
        $connection,
        'zcount',
        ['failed_jobs', '-inf', (string) ($failedDayCutoff->getTimestamp() * -1)],
        9,
    );
    dashboardReturnsFor(
        $connection,
        'zcount',
        ['completed_jobs', '-inf', (string) ($completedHourCutoff->getTimestamp() * -1)],
        6,
    );
    dashboardReturnsFor(
        $connection,
        'zcount',
        ['completed_jobs', '-inf', (string) ($completedDayCutoff->getTimestamp() * -1)],
        31,
    );
    $redis = mockDashboardContract(RedisFactory::class);
    dashboardReturns($redis, 'connection', $connection);

    return new DashboardData(
        $jobs,
        $metrics,
        $supervisors,
        $masterSupervisors,
        $queues,
        $waitTimes,
        unusedQueuePauseStatus(),
        new DashboardPendingState($queues),
        new DashboardBatchSummary($batches, $database),
        $redis,
        app(QueueWaitThreshold::class),
    );
}

describe('DashboardData', function (): void {
    it('builds the running dashboard summary from Horizon contracts', function (): void {
        config()->set('horizon.trim.completed', 120);
        config()->set('horizon.trim.failed', 2880);

        $summary = summaryDashboardData([
            (object) ['status' => 'running'],
            (object) ['status' => 'paused'],
        ])->summary();

        expect($summary->toArray())->toBe([
            'available' => true,
            'status' => 'running',
            'failedJobs' => 23,
            'completedJobs' => 36,
            'pendingJobs' => 5,
            'pendingReserved' => 3,
            'pendingReadyNow' => 8,
            'pendingDelayed' => 11,
            'failedJobsPerMinute' => 0.03,
            'failedJobsPastHour' => 2,
            'failedJobsPastDay' => 9,
            'failedRetentionMinutes' => 2880,
            'completedJobsPerMinute' => 0.1,
            'completedJobsPastHour' => 6,
            'completedJobsPastDay' => 31,
            'completedRetentionMinutes' => 120,
            'activeBatches' => 4,
            'batchPreviews' => [
                [
                    'id' => 'batch-3',
                    'name' => 'Archive audit logs',
                    'progress' => 48,
                ],
                [
                    'id' => 'batch-2',
                    'name' => 'Import order CSV',
                    'progress' => 13,
                ],
                [
                    'id' => 'batch-1',
                    'name' => 'Reindex product catalog',
                    'progress' => 72,
                ],
            ],
            'processes' => 6,
            'waits' => ['redis:default' => 2],
            'maxWaitQueue' => 'redis:default',
            'maxWaitSeconds' => 2,
            'queueWithMaxRuntime' => 'exports',
            'queueWithMaxThroughput' => 'default',
            'message' => null,
        ]);
    });

    it('averages rolling counts over the available retention coverage', function (): void {
        config()->set('horizon.trim.completed', 30);
        config()->set('horizon.trim.failed', 45);

        $summary = summaryDashboardData([(object) ['status' => 'running']])->summary();

        expect($summary->completedJobsPerMinute)->toBe(0.2)
            ->and($summary->failedJobsPerMinute)->toBe(0.04)
            ->and($summary->completedRetentionMinutes)->toBe(30)
            ->and($summary->failedRetentionMinutes)->toBe(45);
    });

    it('reports paused when every Horizon master is paused', function (): void {
        $summary = summaryDashboardData([(object) ['status' => 'paused']])->summary();

        expect($summary->status)->toBe(HorizonStatus::Paused);
    });

    it('reports inactive when Horizon has no masters', function (): void {
        expect(summaryDashboardData([])->summary()->status)->toBe(HorizonStatus::Inactive);
    });

    it('returns a safe unavailable summary when Horizon storage fails', function (): void {
        $masterSupervisors = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardThrows($masterSupervisors, 'all', new RuntimeException('redis secret'));

        $data = new DashboardData(
            mockDashboardContract(JobRepository::class),
            mockDashboardContract(MetricsRepository::class),
            mockDashboardContract(SupervisorRepository::class),
            $masterSupervisors,
            mockDashboardContract(QueueFactory::class),
            mockDashboardContract(WaitTimeCalculator::class),
            unusedQueuePauseStatus(),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        expect($data->summary()->toArray())->toBe([
            'available' => false,
            'status' => 'unavailable',
            'failedJobs' => 0,
            'completedJobs' => 0,
            'pendingJobs' => 0,
            'pendingReserved' => null,
            'pendingReadyNow' => null,
            'pendingDelayed' => null,
            'failedJobsPerMinute' => 0,
            'failedJobsPastHour' => 0,
            'failedJobsPastDay' => 0,
            'failedRetentionMinutes' => 10080,
            'completedJobsPerMinute' => 0,
            'completedJobsPastHour' => 0,
            'completedJobsPastDay' => 0,
            'completedRetentionMinutes' => 60,
            'activeBatches' => 0,
            'batchPreviews' => [],
            'processes' => 0,
            'waits' => [],
            'maxWaitQueue' => null,
            'maxWaitSeconds' => 0,
            'queueWithMaxRuntime' => null,
            'queueWithMaxThroughput' => null,
            'message' => 'Horizon data is currently unavailable.',
        ]);
    });

    it('sorts and normalizes Horizon workload entries', function (): void {
        requireQueuePausing();

        CarbonImmutable::setTestNow('2026-07-20 18:00:00 UTC');

        $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
        dashboardReturns($waitTimes, 'calculate', [
            'redis:mail' => 1,
            'redis:default' => 4.5,
            'redis:reports,exports' => 3,
        ]);
        dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'reports', 2], 2);
        dashboardReturnsFor($waitTimes, 'calculateTimeToClear', ['redis', 'exports', 2], 1);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturns($supervisors, 'all', [
            (object) [
                'processes' => [
                    'redis:default' => 3,
                    'redis:mail' => 1,
                    'redis:reports,exports' => 2,
                ],
            ],
        ]);

        $queue = mockDashboardContract(Queue::class);
        dashboardReturnsFor($queue, 'readyNow', ['default'], 8);
        dashboardReturnsFor($queue, 'readyNow', ['mail'], 2);
        dashboardReturnsFor($queue, 'readyNow', ['reports'], 3);
        dashboardReturnsFor($queue, 'readyNow', ['exports'], 2);
        $queueFactory = mockDashboardContract(QueueFactory::class);
        dashboardReturnsFor($queueFactory, 'connection', ['redis'], $queue);

        $queues = app(QueueManager::class);
        $queues->resume('redis', 'mail');
        $queues->pause('redis', 'default');
        $queues->resume('redis', 'reports');
        $queues->pause('redis', 'exports');
        $metadata = new QueuePauseMetadata(app(CacheFactory::class));
        $metadata->storeUntil('redis', 'exports', CarbonImmutable::parse('2026-07-20 20:00:00 UTC'));
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['default'], 1000);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['mail'], 1000);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['reports'], 1000);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['exports'], 1000);

        $data = new DashboardData(
            mockDashboardContract(JobRepository::class),
            $metrics,
            $supervisors,
            mockDashboardContract(MasterSupervisorRepository::class),
            $queueFactory,
            $waitTimes,
            new QueuePauseStatus($queues, $metadata),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        $workload = $data->workload()->toArray();
        $grouped = $workload['items'][2];

        expect($grouped['waitThreshold']['status'])->toBe('within_bounds')
            ->and($grouped['splitQueues'][0]['waitThreshold']['status'])->toBe('within_bounds')
            ->and($grouped['splitQueues'][1]['waitThreshold']['status'])->toBe('within_bounds');

        foreach ($workload['items'] as &$item) {
            unset($item['waitThreshold']);

            if (is_array($item['splitQueues'])) {
                foreach ($item['splitQueues'] as &$splitQueue) {
                    unset($splitQueue['waitThreshold']);
                }
            }
        }
        unset($item, $splitQueue);

        expect($workload)->toBe([
            'available' => true,
            'items' => [
                [
                    'name' => 'default',
                    'connection' => 'redis',
                    'length' => 8,
                    'wait' => 4.5,
                    'processes' => 3,
                    'paused' => true,
                    'pausedUntil' => null,
                    'splitQueues' => null,
                ],
                [
                    'name' => 'mail',
                    'connection' => 'redis',
                    'length' => 2,
                    'wait' => 1,
                    'processes' => 1,
                    'paused' => false,
                    'pausedUntil' => null,
                    'splitQueues' => null,
                ],
                [
                    'name' => 'reports,exports',
                    'connection' => 'redis',
                    'length' => 5,
                    'wait' => 3,
                    'processes' => 2,
                    'paused' => false,
                    'pausedUntil' => null,
                    'splitQueues' => [
                        ['name' => 'reports', 'length' => 3, 'wait' => 2, 'paused' => false, 'pausedUntil' => null],
                        ['name' => 'exports', 'length' => 2, 'wait' => 3, 'paused' => true, 'pausedUntil' => 1784577600],
                    ],
                ],
            ],
            'message' => null,
        ]);
    });

    it('exposes the wait threshold state for each workload queue', function (): void {
        config()->set('horizon.waits.redis:default', 30);

        $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
        dashboardReturns($waitTimes, 'calculate', ['redis:default' => 31]);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturns($supervisors, 'all', [
            (object) ['processes' => ['redis:default' => 2]],
        ]);

        $queue = mockDashboardContract(Queue::class);
        dashboardReturnsFor($queue, 'readyNow', ['default'], 3);
        dashboardReturnsFor($queue, 'creationTimeOfOldestPendingJob', ['default'], null);
        $queueFactory = mockDashboardContract(QueueFactory::class);
        dashboardReturnsFor($queueFactory, 'connection', ['redis'], $queue);
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['default'], 1000);

        $data = new DashboardData(
            mockDashboardContract(JobRepository::class),
            $metrics,
            $supervisors,
            mockDashboardContract(MasterSupervisorRepository::class),
            $queueFactory,
            $waitTimes,
            unusedQueuePauseStatus(),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        $item = $data->workload()->toArray()['items'][0];

        expect($item['name'])->toBe('default')
            ->and($item['waitThreshold']['status'])->toBe('exceeded')
            ->and($item['waitThreshold']['waitSeconds'])->toBe(31)
            ->and($item['waitThreshold']['thresholdSeconds'])->toBe(30);
    });

    it('does not mark a workload threshold exceeded before runtime data is available', function (): void {
        config()->set('horizon.waits.redis:default', 30);

        $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
        dashboardReturns($waitTimes, 'calculate', ['redis:default' => 31]);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturns($supervisors, 'all', [
            (object) ['processes' => ['redis:default' => 2]],
        ]);

        $queue = mockDashboardContract(Queue::class);
        dashboardReturnsFor($queue, 'readyNow', ['default'], 3);
        dashboardReturnsFor($queue, 'creationTimeOfOldestPendingJob', ['default'], null);
        $queueFactory = mockDashboardContract(QueueFactory::class);
        dashboardReturnsFor($queueFactory, 'connection', ['redis'], $queue);

        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['default'], 0);

        $data = new DashboardData(
            mockDashboardContract(JobRepository::class),
            $metrics,
            $supervisors,
            mockDashboardContract(MasterSupervisorRepository::class),
            $queueFactory,
            $waitTimes,
            unusedQueuePauseStatus(),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        $threshold = $data->workload()->toArray()['items'][0]['waitThreshold'];

        expect($threshold['status'])->toBe('calculating')
            ->and($threshold['waitSeconds'])->toBeNull();
    });

    it('binds each workload row to its own connection when the same queue exists on multiple connections', function (): void {
        requireQueuePausing();

        CarbonImmutable::setTestNow('2026-07-20 18:00:00 UTC');

        // One authoritative calculate() snapshot retains connection:queue keys. The same
        // queue name on two connections must keep independent pause targeting.
        $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
        dashboardReturns($waitTimes, 'calculate', [
            'redis-b:shared' => 100,
            'redis-a:shared' => 50,
        ]);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturns($supervisors, 'all', [
            (object) [
                'processes' => [
                    'redis-a:shared' => 2,
                    'redis-b:shared' => 1,
                ],
            ],
        ]);

        $queueA = mockDashboardContract(Queue::class);
        dashboardReturnsFor($queueA, 'readyNow', ['shared'], 9);
        $queueB = mockDashboardContract(Queue::class);
        dashboardReturnsFor($queueB, 'readyNow', ['shared'], 1);
        $queueFactory = mockDashboardContract(QueueFactory::class);
        dashboardReturnsFor($queueFactory, 'connection', ['redis-a'], $queueA);
        dashboardReturnsFor($queueFactory, 'connection', ['redis-b'], $queueB);

        $queues = app(QueueManager::class);
        $queues->pause('redis-a', 'shared');
        $queues->resume('redis-b', 'shared');

        $data = new DashboardData(
            mockDashboardContract(JobRepository::class),
            mockDashboardContract(MetricsRepository::class),
            $supervisors,
            mockDashboardContract(MasterSupervisorRepository::class),
            $queueFactory,
            $waitTimes,
            new QueuePauseStatus($queues, new QueuePauseMetadata(app(CacheFactory::class))),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        $items = $data->workload()->toArray()['items'];
        $byConnection = [];

        foreach ($items as $item) {
            $byConnection[$item['connection']] = $item;
        }

        expect($byConnection)->toHaveKeys(['redis-a', 'redis-b'])
            ->and($byConnection['redis-a'])->toMatchArray([
                'name' => 'shared',
                'connection' => 'redis-a',
                'length' => 9,
                'wait' => 50,
                'processes' => 2,
                'paused' => true,
                'pausedUntil' => null,
                'splitQueues' => null,
            ])
            ->and($byConnection['redis-b'])->toMatchArray([
                'name' => 'shared',
                'connection' => 'redis-b',
                'length' => 1,
                'wait' => 100,
                'processes' => 1,
                'paused' => false,
                'pausedUntil' => null,
                'splitQueues' => null,
            ]);
    });

    it('exposes safe recent failure previews without payloads or exceptions', function (): void {
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturns($jobs, 'getFailed', new Collection([
            (object) [
                'id' => 'job-1',
                'name' => 'App\\Jobs\\ImportFeed',
                'queue' => 'default',
                'failed_at' => '1784281320.25',
                'payload' => '{"secret":"value"}',
                'exception' => 'Sensitive trace',
            ],
        ]));

        $data = new DashboardData(
            $jobs,
            mockDashboardContract(MetricsRepository::class),
            mockDashboardContract(SupervisorRepository::class),
            mockDashboardContract(MasterSupervisorRepository::class),
            mockDashboardContract(QueueFactory::class),
            mockDashboardContract(WaitTimeCalculator::class),
            unusedQueuePauseStatus(),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        expect($data->recentFailures()->toArray())->toBe([
            'available' => true,
            'items' => [[
                'id' => 'job-1',
                'name' => 'App\\Jobs\\ImportFeed',
                'queue' => 'default',
                'failedAt' => 1784281320.25,
            ]],
            'message' => null,
        ]);
    });

    it('groups and normalizes Horizon supervisors', function (): void {
        MasterSupervisor::determineNameUsing(static fn (): string => 'web');
        $masterName = MasterSupervisor::basename().'-ABCD';
        $remoteMasterName = 'web-2-EFGH';
        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturns($supervisors, 'all', [
            (object) [
                'name' => $masterName.':supervisor-1',
                'master' => $masterName,
                'status' => 'running',
                'processes' => ['redis:default,emails' => 4],
                'options' => ['connection' => 'redis', 'balance' => 'auto'],
            ],
            (object) [
                'name' => $masterName.':supervisor-2',
                'master' => $masterName,
                'status' => 'running',
                'processes' => ['redis:mail' => 1],
                'options' => ['connection' => 'redis', 'balance' => false],
            ],
            (object) [
                'name' => $remoteMasterName.':supervisor-remote',
                'master' => $remoteMasterName,
                'status' => 'running',
                'processes' => ['redis:remote' => 2],
                'options' => ['connection' => 'redis', 'balance' => 'auto'],
            ],
        ]);

        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardReturns($masters, 'all', [
            (object) [
                'name' => $masterName,
                'environment' => 'production',
                'pid' => '8124',
                'status' => 'running',
            ],
            (object) [
                'name' => $remoteMasterName,
                'environment' => 'production',
                'pid' => '9124',
                'status' => 'running',
            ],
        ]);

        $data = new DashboardData(
            mockDashboardContract(JobRepository::class),
            mockDashboardContract(MetricsRepository::class),
            $supervisors,
            $masters,
            mockDashboardContract(QueueFactory::class),
            mockDashboardContract(WaitTimeCalculator::class),
            unusedQueuePauseStatus(),
            unusedDashboardPendingState(),
            unusedDashboardBatchSummary(),
            mockDashboardContract(RedisFactory::class),
            app(QueueWaitThreshold::class),
        );

        expect($data->supervisors()->toArray())->toBe([
            'available' => true,
            'groups' => [[
                'name' => $remoteMasterName,
                'environment' => 'production',
                'pid' => 9124,
                'status' => 'running',
                'local' => false,
                'items' => [[
                    'id' => $remoteMasterName.':supervisor-remote',
                    'name' => 'supervisor-remote',
                    'connection' => 'redis',
                    'queues' => ['remote'],
                    'processes' => 2,
                    'balancing' => 'Auto',
                    'status' => 'running',
                ]],
            ], [
                'name' => $masterName,
                'environment' => 'production',
                'pid' => 8124,
                'status' => 'running',
                'local' => true,
                'items' => [[
                    'id' => $masterName.':supervisor-1',
                    'name' => 'supervisor-1',
                    'connection' => 'redis',
                    'queues' => ['default', 'emails'],
                    'processes' => 4,
                    'balancing' => 'Auto',
                    'status' => 'running',
                ], [
                    'id' => $masterName.':supervisor-2',
                    'name' => 'supervisor-2',
                    'connection' => 'redis',
                    'queues' => ['mail'],
                    'processes' => 1,
                    'balancing' => 'Disabled',
                    'status' => 'running',
                ]],
            ]],
            'message' => null,
        ]);
    });
});

function unusedDashboardPendingState(): DashboardPendingState
{
    return new DashboardPendingState(mockDashboardContract(QueueFactory::class));
}

function unusedDashboardBatchSummary(): DashboardBatchSummary
{
    return new DashboardBatchSummary(
        mockDashboardContract(BatchRepository::class),
        mockDashboardContract(ConnectionResolverInterface::class),
    );
}

function unusedQueuePauseStatus(): QueuePauseStatus
{
    return new QueuePauseStatus(
        app(QueueManager::class),
        new QueuePauseMetadata(app(CacheFactory::class)),
    );
}
