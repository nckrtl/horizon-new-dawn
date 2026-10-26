<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests\Support;

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use Laravel\Horizon\SupervisorCommands\Pause;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\ForgetsPendingJob;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Metrics\SnapshotJobsPerMinute;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringData;
use NckRtl\HorizonNewDawn\Queues\QueueActivityData;
use NckRtl\HorizonNewDawn\Queues\QueueBatchesData;
use NckRtl\HorizonNewDawn\Queues\QueueJobsData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueSummary;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Supervisors\SupervisorDetails;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

function bindBrowserPageFixtures(
    int $pendingJobCount = 0,
    int $completedJobCount = 0,
    int $failedJobCount = 1,
    int $silencedJobCount = 1,
): void {
    Horizon::auth(static fn (): bool => true);
    // Browser suites must not require live Redis or execute bulk workers. Fake the
    // bus and use an async bulk connection name so BulkOperationDispatcher accepts
    // the queue while Bus::fake() records the coordinator job for toast success.
    Bus::fake();
    config()->set('horizon-new-dawn.poll_interval', 0);
    config()->set('horizon-new-dawn.bulk_operations.connection', 'operations');
    config()->set('horizon-new-dawn.bulk_operations.queue', 'horizon-maintenance');
    config()->set('queue.connections.redis.retry_after', 120);
    $capabilities = new FrameworkCapabilities(queuePausing: false, timedQueuePausing: false);
    app()->instance(FrameworkCapabilities::class, $capabilities);

    // LocalInstanceName requires basename + '-' + exactly four alphanumerics.
    $instanceName = MasterSupervisor::basename().'-br01';
    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [
        (object) [
            'name' => $instanceName,
            'environment' => 'testing',
            'pid' => 1204,
            'status' => 'running',
        ],
    ]);
    // DashboardData resolves these contracts from the container — binding is
    // required (see bindBrowserSupervisorScalingFixtures), not only constructing mocks.
    app()->instance(MasterSupervisorRepository::class, $masters);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));

    $pending = horizonJob(0, 'pending-1');
    $pending->status = 'pending';
    $pending->completed_at = null;

    $completed = horizonJob(1, 'completed-1');
    $silenced = horizonJob(2, 'silenced-1');
    $silenced->queue = 'reports';
    $recent = horizonJob(3, 'recent-1');

    $failed = horizonJob(4, 'failed-1');
    $failed->status = 'failed';
    $failed->reserved_at = '1784281003.40';
    $failed->completed_at = null;
    $failed->failed_at = '1784281003.50';

    $batchFailedParent = horizonJob(5, 'batch-failed-parent');
    $batchFailedParent->status = 'failed';
    $batchFailedParent->completed_at = null;
    $batchFailedParent->failed_at = '1784281004.50';
    $batchParentPayload = json_decode($batchFailedParent->payload, true, flags: JSON_THROW_ON_ERROR);
    $batchParentPayload['attempts'] = 1;
    $batchParentPayload['data']['batchId'] = 'batch-1';
    $batchFailedParent->payload = json_encode($batchParentPayload, JSON_THROW_ON_ERROR);
    $batchFailedParent->retried_by = json_encode([
        ['id' => 'batch-failed-retry', 'status' => 'failed'],
    ], JSON_THROW_ON_ERROR);

    $batchFailedRetry = horizonJob(6, 'batch-failed-retry');
    $batchFailedRetry->status = 'failed';
    $batchFailedRetry->completed_at = null;
    $batchFailedRetry->failed_at = '1784281005.50';
    $batchRetryPayload = json_decode($batchFailedRetry->payload, true, flags: JSON_THROW_ON_ERROR);
    $batchRetryPayload['attempts'] = 2;
    $batchRetryPayload['retry_of'] = 'batch-failed-parent';
    $batchRetryPayload['data']['batchId'] = 'batch-1';
    $batchFailedRetry->payload = json_encode($batchRetryPayload, JSON_THROW_ON_ERROR);

    $jobsById = [
        $pending->id => $pending,
        $completed->id => $completed,
        $silenced->id => $silenced,
        $recent->id => $recent,
        $failed->id => $failed,
        $batchFailedParent->id => $batchFailedParent,
        $batchFailedRetry->id => $batchFailedRetry,
    ];

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsUsing(
        $jobs,
        'getJobs',
        static fn (array $ids, int $startingAt = 0): Collection => new Collection(array_values(array_filter(
            array_map(static fn (string $id): ?object => $jobsById[$id] ?? null, $ids),
        ))),
    );
    dashboardReturns($jobs, 'getPending', new Collection);
    dashboardReturns($jobs, 'getCompleted', new Collection);
    dashboardReturns($jobs, 'getFailed', new Collection([$failed]));
    dashboardReturnsUsing($jobs, 'findFailed', static fn (string $id): ?object => $jobsById[$id] ?? null);
    dashboardReturns($jobs, 'getSilenced', new Collection([$silenced]));
    dashboardReturns($jobs, 'countPending', $pendingJobCount);
    dashboardReturns($jobs, 'countCompleted', $completedJobCount);
    dashboardReturns($jobs, 'countFailed', $failedJobCount);
    dashboardReturns($jobs, 'countRecentlyFailed', $failedJobCount);
    dashboardReturns($jobs, 'countRecent', $completedJobCount);
    dashboardReturns($jobs, 'countSilenced', $silencedJobCount);
    app()->instance(JobRepository::class, $jobs);

    $jobData = new JobsData($jobs);
    $tags = mockDashboardContract(TagRepository::class);
    dashboardReturns($tags, 'monitoring', ['checkout']);
    dashboardReturnsUsing($tags, 'count', static fn (string $tag): int => match ($tag) {
        'checkout' => 1,
        'failed:checkout' => 1,
        default => 0,
    });
    dashboardReturnsUsing(
        $tags,
        'paginate',
        static fn (string $tag, int $startingAt = 0, int $limit = 50): array => match ($tag) {
            'checkout' => ['recent-1'],
            'failed:checkout' => ['failed-1'],
            default => [],
        },
    );
    app()->instance(TagRepository::class, $tags);

    $failedJobs = new FailedJobsData(
        $jobs,
        $tags,
        $jobData,
        new FailedJobRetryEligibility,
    );

    app()->instance(JobsData::class, $jobData);
    app()->instance(FailedJobsData::class, $failedJobs);
    app()->instance(MonitoringData::class, new MonitoringData($tags, $jobs, $jobData));

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturns($metrics, 'measuredJobs', ['App\\Jobs\\SyncInventory']);
    dashboardReturns($metrics, 'measuredQueues', ['default']);
    dashboardReturns($metrics, 'snapshotsForJob', [
        (object) ['time' => 1784588400, 'throughput' => 12, 'runtime' => 100],
    ]);
    dashboardReturns($metrics, 'snapshotsForQueue', [
        (object) ['time' => 1784588400, 'throughput' => 8, 'runtime' => 2000],
    ]);
    dashboardReturns($metrics, 'throughput', 0);
    dashboardReturns($metrics, 'throughputForJob', 12);
    dashboardReturns($metrics, 'throughputForQueue', 0);
    dashboardReturns($metrics, 'runtimeForJob', 1500);
    dashboardReturns($metrics, 'runtimeForQueue', 2000);
    app()->instance(MetricsRepository::class, $metrics);
    app()->instance(MetricsData::class, new MetricsData($metrics));

    $batch = horizonBatch(
        'batch-1',
        name: 'Import customer records',
        totalJobs: 1,
        pendingJobs: 1,
        failedJobs: 2,
        failedJobIds: ['batch-failed-parent', 'batch-failed-retry'],
    );
    $batchRepository = mockDashboardContract(BatchRepository::class);
    dashboardReturns($batchRepository, 'find', $batch);
    dashboardReturns($batchRepository, 'get', []);
    $batches = new BatchesData(
        $batchRepository,
        new BatchJobsData($jobs, $jobData),
    );
    app()->instance(BatchRepository::class, $batchRepository);
    app()->instance(BatchesData::class, $batches);

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    $supervisorRecord = (object) [
        'name' => $instanceName.':supervisor-1',
        'master' => $instanceName,
        'status' => 'running',
        'processes' => ['redis:reports' => 2, 'redis:critical,default' => 4],
        'options' => [
            'connection' => 'redis',
            'queue' => 'critical,default',
            'balance' => 'auto',
            'timeout' => 90,
        ],
    ];
    dashboardReturns($supervisors, 'all', [$supervisorRecord]);
    dashboardReturns($supervisors, 'find', $supervisorRecord);
    app()->instance(SupervisorRepository::class, $supervisors);
    app()->instance(SupervisorDetails::class, new SupervisorDetails(
        $supervisors,
        app(ConfigRepository::class),
    ));

    $queue = mockDashboardContract(Queue::class);
    dashboardReturns($queue, 'readyNow', 1);
    dashboardReturns($queue, 'reservedSize', 0);
    dashboardReturns($queue, 'delayedSize', 1);
    dashboardReturns($queue, 'creationTimeOfOldestPendingJob', null);
    $queueFactory = mockDashboardContract(QueueFactory::class);
    dashboardReturns($queueFactory, 'connection', $queue);
    app()->instance(QueueFactory::class, $queueFactory);

    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturns($waitTimes, 'calculate', [
        'redis:reports' => 1,
        'redis:critical,default' => 2,
    ]);
    dashboardReturns($waitTimes, 'calculateTimeToClear', 0);
    app()->instance(WaitTimeCalculator::class, $waitTimes);

    // Dashboard summary reads failed-job windows and snapshot leaders from the
    // horizon Redis connection; stub it so the no-Redis CI tests job stays offline.
    $horizonConnection = mockDashboardContract(Connection::class);
    dashboardReturns($horizonConnection, 'zcount', 0);
    dashboardReturns($horizonConnection, 'zrange', []);
    dashboardReturns($horizonConnection, 'get', null);
    $redis = mockDashboardContract(RedisFactory::class);
    dashboardReturns($redis, 'connection', $horizonConnection);
    app()->instance(RedisFactory::class, $redis);
    app()->instance(SnapshotJobsPerMinute::class, new SnapshotJobsPerMinute($redis));

    // Async bulk connection for BulkOperationDispatcher (must not be Sync/Null).
    // Bus::fake() above records coordinator jobs without executing Redis workers.
    app()->instance(QueueManager::class, new BrowserPendingJobQueueManager(
        app(),
        new BrowserPendingJobRedisQueue(new BrowserPendingJobRedisConnection),
    ));

    $pauseStatus = new QueuePauseStatus(
        app(QueueManager::class),
        new QueuePauseMetadata(app(CacheFactory::class)),
        $capabilities,
    );
    app()->instance(QueuesData::class, new QueuesData(
        $supervisors,
        $queueFactory,
        $waitTimes,
        $metrics,
        $pauseStatus,
        new QueueWaitThreshold(app(ConfigRepository::class)),
    ));

    $queueJobs = new QueueJobsData(
        $jobs,
        $jobData,
        $failedJobs,
        app(CacheFactory::class),
    );
    $queueBatches = new QueueBatchesData(
        $batchRepository,
        $batches,
        app(CacheFactory::class),
    );
    app()->instance(QueueJobsData::class, $queueJobs);
    app()->instance(QueueBatchesData::class, $queueBatches);
    app()->instance(QueueSummary::class, new QueueSummary(
        $queueJobs,
        $queueBatches,
        $metrics,
        app(SnapshotJobsPerMinute::class),
    ));
    app()->instance(QueueActivityData::class, new QueueActivityData($queueJobs, $queueBatches));

    app()->instance(ForgetsPendingJob::class, new class implements ForgetsPendingJob
    {
        public function forgetPending(string $id, array $tags): bool
        {
            return true;
        }
    });
}

function bindBrowserQueueCompletedSummaryRefreshFixtures(): void
{
    bindBrowserPageFixtures();
    config()->set('horizon-new-dawn.poll_interval', 500);

    $summaryAttempt = 0;
    $completedJobs = static function (int $count): Collection {
        return new Collection(array_map(
            static function (int $index): object {
                $job = horizonJob($index, "completed-summary-{$index}");
                $job->queue = 'reports';
                $job->status = 'completed';

                return $job;
            },
            range(1, $count),
        ));
    };
    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countPending', 0);
    dashboardReturnsUsing(
        $jobs,
        'countCompleted',
        static function () use (&$summaryAttempt): int {
            $summaryAttempt++;

            return $summaryAttempt >= 3 ? 37 : 36;
        },
    );
    dashboardReturnsUsing(
        $jobs,
        'getCompleted',
        static function (string $cursor = '-1') use (
            &$summaryAttempt,
            $completedJobs,
        ): Collection {
            if ($summaryAttempt === 2) {
                throw new \RuntimeException(
                    'The completed summary is temporarily unavailable.',
                );
            }

            return $completedJobs($summaryAttempt >= 3 ? 37 : 36);
        },
    );
    dashboardReturns($jobs, 'countFailed', 0);
    dashboardReturns($jobs, 'countRecentlyFailed', 0);
    dashboardReturns($jobs, 'countRecent', 36);
    dashboardReturns($jobs, 'countSilenced', 0);

    $jobData = new JobsData($jobs);
    $failedJobs = new FailedJobsData(
        $jobs,
        app(TagRepository::class),
        $jobData,
        new FailedJobRetryEligibility,
    );
    $queueJobs = new QueueJobsData(
        $jobs,
        $jobData,
        $failedJobs,
        app(CacheFactory::class),
    );

    app()->instance(QueueSummary::class, new QueueSummary(
        $queueJobs,
        app(QueueBatchesData::class),
        app(MetricsRepository::class),
        app(SnapshotJobsPerMinute::class),
    ));
}

function bindBrowserFailedJobBulkLimitFixtures(): void
{
    bindBrowserPageFixtures();

    $failed = horizonJob(0, 'failed-over-limit');
    $failed->status = 'failed';
    $failed->completed_at = null;
    $failed->failed_at = '1784281003.50';

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'getFailed', new Collection([$failed]));
    dashboardReturns($jobs, 'countFailed', 2);
    app()->instance(FailedJobsData::class, new FailedJobsData(
        $jobs,
        app(TagRepository::class),
        new JobsData($jobs),
        new FailedJobRetryEligibility,
    ));
}

/** @return array{failedJobId: string, batchId: string} */
function bindBrowserFailedJobIdentifierOverflowFixtures(): array
{
    bindBrowserPageFixtures();

    $failedJobId = '018f7f45-6a22-7d5d-8f4c-9b032d6e7a81';
    $batchId = '018f7f45-6a22-7d5d-8f4c-9b032d6e7a82';
    $failed = horizonJob(0, $failedJobId);
    $failed->status = 'failed';
    $failed->completed_at = null;
    $failed->failed_at = '1784281003.50';
    $payload = json_decode($failed->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['data']['batchId'] = $batchId;
    $failed->payload = json_encode($payload, JSON_THROW_ON_ERROR);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'findFailed', $failed);
    $jobData = new JobsData($jobs);

    app()->instance(JobRepository::class, $jobs);
    app()->instance(JobsData::class, $jobData);
    app()->instance(FailedJobsData::class, new FailedJobsData(
        $jobs,
        app(TagRepository::class),
        $jobData,
        new FailedJobRetryEligibility,
    ));

    return [
        'failedJobId' => $failedJobId,
        'batchId' => $batchId,
    ];
}

function bindBrowserSupervisorScalingFixtures(): void
{
    bindBrowserPageFixtures();
    config()->set('horizon-new-dawn.poll_interval', 2000);

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [
        (object) [
            'name' => 'horizon-web-01',
            'environment' => 'production',
            'pid' => 1204,
            'status' => 'running',
        ],
    ]);
    app()->instance(MasterSupervisorRepository::class, $masters);

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        browserScalingSupervisor('idle-above-minimum', 'idle-above-minimum', 6, 1, 10, 'time'),
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $queue = mockDashboardContract(Queue::class);
    dashboardReturnsUsing(
        $queue,
        'readyNow',
        static fn (string $queueName): int => $queueName === 'idle-above-minimum' ? 4 : 0,
    );
    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturns($queues, 'connection', $queue);
    app()->instance(QueueFactory::class, $queues);
}

function bindBrowserProcessTransitionFixtures(bool $supervisorPaused = true): string
{
    bindBrowserPageFixtures();
    config()->set('horizon-new-dawn.poll_interval', 2000);

    $instance = MasterSupervisor::basename().'-a1b2';
    $supervisor = $instance.':supervisor-1';
    $masterRecord = (object) [
        'name' => $instance,
        'environment' => 'testing',
        'pid' => 1204,
        'status' => 'running',
    ];
    $supervisorRecord = (object) [
        'name' => $supervisor,
        'master' => $instance,
        'status' => $supervisorPaused ? 'paused' : 'running',
        'processes' => ['redis:default' => 1],
        'options' => [
            'connection' => 'redis',
            'queue' => 'default',
            'balance' => 'auto',
        ],
    ];
    $transitionState = new class
    {
        public ?string $pendingCommand = null;

        public bool $holdPendingCommand = false;
    };

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturnsUsing(
        $masters,
        'all',
        static function () use (
            $masterRecord,
            $transitionState,
        ): array {
            $transitionState->holdPendingCommand = $transitionState->pendingCommand !== null;

            return [clone $masterRecord];
        },
    );
    dashboardReturns($masters, 'find', $masterRecord);
    app()->instance(MasterSupervisorRepository::class, $masters);

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturnsUsing(
        $supervisors,
        'all',
        static function () use (
            $masterRecord,
            $supervisorRecord,
            $transitionState,
        ): array {
            $record = clone $supervisorRecord;

            if (
                $transitionState->holdPendingCommand
                && $transitionState->pendingCommand === 'pause'
            ) {
                $masterRecord->status = 'paused';
                $supervisorRecord->status = 'paused';
            } elseif (
                $transitionState->holdPendingCommand
                && $transitionState->pendingCommand === 'continue'
            ) {
                $supervisorRecord->status = 'running';
            }

            if ($transitionState->holdPendingCommand) {
                $transitionState->holdPendingCommand = false;
                $transitionState->pendingCommand = null;
            }

            return [$record];
        },
    );
    dashboardReturns($supervisors, 'find', $supervisorRecord);
    app()->instance(SupervisorRepository::class, $supervisors);

    $commands = mockDashboardContract(HorizonCommandQueue::class);
    dashboardReturnsUsing(
        $commands,
        'push',
        static function (string $queue, string $command) use (
            $instance,
            $supervisor,
            $transitionState,
        ): void {
            if (
                $queue === MasterSupervisor::commandQueueFor($instance)
                && $command === Pause::class
            ) {
                $transitionState->pendingCommand = 'pause';

                return;
            }

            if ($queue === $supervisor && $command === ContinueWorking::class) {
                $transitionState->pendingCommand = 'continue';
            }
        },
    );
    app()->instance(HorizonCommandQueue::class, $commands);

    return $instance;
}

function browserScalingSupervisor(
    string $name,
    string $queue,
    int $processes,
    int $minProcesses,
    int $maxProcesses,
    string $strategy,
): object {
    return (object) [
        'name' => "horizon-web-01:{$name}",
        'master' => 'horizon-web-01',
        'status' => 'running',
        'processes' => ["redis:{$queue}" => $processes],
        'options' => [
            'connection' => 'redis',
            'queue' => $queue,
            'balance' => 'auto',
            'minProcesses' => $minProcesses,
            'maxProcesses' => $maxProcesses,
            'autoScalingStrategy' => $strategy,
        ],
    ];
}

function bindBrowserInfiniteScrollRefreshFixtures(bool $emptyOnRefresh = false): void
{
    bindBrowserPageFixtures();
    config()->set('horizon-new-dawn.poll_interval', 2000);

    $failedJob = static function (int $index): HorizonJob {
        $job = horizonJob($index, "failed-{$index}");
        $job->status = 'failed';
        $job->completed_at = null;
        $job->failed_at = (string) (1784281003.5 + $index);

        return $job;
    };
    $initialFirstPage = array_map($failedJob, range(149, 100));
    $secondPage = array_map($failedJob, range(99, 50));
    $thirdPage = array_map($failedJob, range(49, 1));
    $newJob = $failedJob(150);
    $updatedJob = clone $initialFirstPage[0];
    $updatedJob->name = 'App\\Jobs\\RefreshedImportFeed';
    $refreshedFirstPage = $emptyOnRefresh
        ? []
        : [$newJob, $updatedJob, ...array_slice($initialFirstPage, 1, 48)];
    // Full document loads call getFailed('-1') more than once (list page + bulk
    // retry scan). Only Inertia partials that include jobs should advance to the
    // refreshed first-page snapshot used by automatic refresh tests.
    $useRefreshedFirstPage = false;

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsUsing(
        $jobs,
        'getFailed',
        static function (mixed $startingAt) use (
            &$useRefreshedFirstPage,
            $initialFirstPage,
            $refreshedFirstPage,
            $secondPage,
            $thirdPage,
        ): Collection {
            $startingAt = (string) $startingAt;

            if ($startingAt === '100') {
                return new Collection($secondPage);
            }

            if ($startingAt === '50') {
                return new Collection($thirdPage);
            }

            if ($startingAt !== '-1') {
                return new Collection;
            }

            $partialData = (string) request()->header('X-Inertia-Partial-Data', '');

            if ($partialData !== '' && str_contains($partialData, 'jobs')) {
                $useRefreshedFirstPage = true;
            }

            return new Collection(
                $useRefreshedFirstPage ? $refreshedFirstPage : $initialFirstPage,
            );
        },
    );
    dashboardReturns($jobs, 'getJobs', new Collection);
    dashboardReturns($jobs, 'getPending', new Collection);
    dashboardReturns($jobs, 'getCompleted', new Collection);
    dashboardReturns($jobs, 'getSilenced', new Collection);
    dashboardReturns($jobs, 'countPending', 0);
    dashboardReturns($jobs, 'countCompleted', 0);
    dashboardReturnsUsing(
        $jobs,
        'countFailed',
        static function () use (&$useRefreshedFirstPage, $emptyOnRefresh): int {
            return $emptyOnRefresh && $useRefreshedFirstPage ? 0 : 150;
        },
    );
    dashboardReturns($jobs, 'countRecentlyFailed', 0);
    dashboardReturns($jobs, 'countRecent', 0);
    dashboardReturns($jobs, 'countSilenced', 0);
    app()->instance(JobRepository::class, $jobs);

    $jobData = new JobsData($jobs);
    app()->instance(JobsData::class, $jobData);
    app()->instance(FailedJobsData::class, new FailedJobsData(
        $jobs,
        app(TagRepository::class),
        $jobData,
        new FailedJobRetryEligibility,
    ));
}

final class BrowserPendingJobQueueManager extends QueueManager
{
    public function __construct($app, private readonly Queue $queue)
    {
        parent::__construct($app);
    }

    public function connection($name = null): Queue
    {
        return $this->queue;
    }
}

final class BrowserPendingJobRedisQueue extends RedisQueue
{
    public function __construct(private readonly Connection $redisConnection) {}

    public function getConnection(): Connection
    {
        return $this->redisConnection;
    }

    public function getQueueRedisKey($queue = null): string
    {
        $name = \Illuminate\Support\enum_value($queue);

        return 'queues:'.(is_string($name) && $name !== '' ? $name : 'default');
    }
}

final class BrowserPendingJobRedisConnection extends Connection
{
    /** @param array<int, string>|string $channels */
    public function createSubscription($channels, $callback, $method = 'subscribe'): void {}

    /** @param array<int, mixed> $parameters */
    public function command($method, array $parameters = []): int
    {
        return 1;
    }
}
