<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests\Support;

use Closure;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\ForgetsPendingJob;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
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

function bindBrowserPageFixtures(): void
{
    Horizon::auth(static fn (): bool => true);
    config()->set('horizon-new-dawn.poll_interval', 0);
    config()->set('queue.connections.redis.retry_after', 120);

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
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
    $failed->completed_at = null;
    $failed->failed_at = '1784281003.50';

    $jobsById = [
        $pending->id => $pending,
        $completed->id => $completed,
        $silenced->id => $silenced,
        $recent->id => $recent,
        $failed->id => $failed,
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
    dashboardReturns($jobs, 'getFailed', new Collection);
    dashboardReturns($jobs, 'getSilenced', new Collection([$silenced]));
    dashboardReturns($jobs, 'countPending', 0);
    dashboardReturns($jobs, 'countCompleted', 0);
    dashboardReturns($jobs, 'countFailed', 0);
    dashboardReturns($jobs, 'countSilenced', 1);
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
        (object) ['time' => 1784588400, 'throughput' => 12, 'runtime' => 1500],
    ]);
    dashboardReturns($metrics, 'snapshotsForQueue', [
        (object) ['time' => 1784588400, 'throughput' => 8, 'runtime' => 2000],
    ]);
    dashboardReturns($metrics, 'throughputForJob', 12);
    dashboardReturns($metrics, 'throughputForQueue', 0);
    dashboardReturns($metrics, 'runtimeForJob', 1500);
    dashboardReturns($metrics, 'runtimeForQueue', 2000);
    app()->instance(MetricsData::class, new MetricsData($metrics));

    $batch = horizonBatch(
        'batch-1',
        name: 'Import customer records',
        totalJobs: 0,
        pendingJobs: 0,
    );
    $batchRepository = mockDashboardContract(BatchRepository::class);
    dashboardReturns($batchRepository, 'find', $batch);
    dashboardReturns($batchRepository, 'get', []);
    $batchDatabase = mockDashboardContract(ConnectionResolverInterface::class);
    $batchConnection = mockDashboardContract(ConnectionInterface::class);
    $batchQuery = mockDashboardContract(Builder::class);
    dashboardReturns($batchDatabase, 'connection', $batchConnection);
    dashboardReturns($batchConnection, 'table', $batchQuery);
    dashboardReturnsUsing(
        $batchQuery,
        'where',
        static function (mixed $column) use ($batchQuery): Builder {
            if ($column instanceof Closure) {
                $column($batchQuery);
            }

            return $batchQuery;
        },
    );
    dashboardReturns($batchQuery, 'orWhere', $batchQuery);
    dashboardReturns($batchQuery, 'orderByDesc', $batchQuery);
    dashboardReturns($batchQuery, 'limit', $batchQuery);
    dashboardReturns($batchQuery, 'pluck', collect());
    dashboardReturns($batchQuery, 'whereNotNull', $batchQuery);
    dashboardReturns($batchQuery, 'orWhereNotNull', $batchQuery);
    dashboardReturns($batchQuery, 'count', 0);
    $batches = new BatchesData(
        $batchRepository,
        new BatchJobsData($jobs, $jobData),
        $batchDatabase,
    );
    app()->instance(BatchesData::class, $batches);

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:reports' => 2]],
    ]);
    dashboardReturns($supervisors, 'find', (object) [
        'name' => 'horizon-web-01:supervisor-1',
        'master' => 'horizon-web-01',
        'status' => 'running',
        'processes' => ['redis:critical,default' => 4],
        'options' => [
            'connection' => 'redis',
            'queue' => 'critical,default',
            'balance' => 'auto',
            'timeout' => 90,
        ],
    ]);
    app()->instance(SupervisorDetails::class, new SupervisorDetails(
        $supervisors,
        app(ConfigRepository::class),
    ));

    $queue = mockDashboardContract(Queue::class);
    dashboardReturns($queue, 'readyNow', 0);
    dashboardReturns($queue, 'reservedSize', 0);
    dashboardReturns($queue, 'delayedSize', 0);
    dashboardReturns($queue, 'creationTimeOfOldestPendingJob', null);
    $queueFactory = mockDashboardContract(QueueFactory::class);
    dashboardReturns($queueFactory, 'connection', $queue);
    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturns($waitTimes, 'calculateTimeToClear', 0);
    $pauseStatus = new QueuePauseStatus(
        app(QueueManager::class),
        new QueuePauseMetadata(app(CacheFactory::class)),
        new FrameworkCapabilities(queuePausing: false),
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
    app()->instance(QueueSummary::class, new QueueSummary($queueJobs, $queueBatches, $metrics));
    app()->instance(QueueActivityData::class, new QueueActivityData($queueJobs, $queueBatches));

    app()->instance(QueueManager::class, new BrowserPendingJobQueueManager(
        app(),
        new BrowserPendingJobRedisQueue(new BrowserPendingJobRedisConnection),
    ));
    app()->instance(ForgetsPendingJob::class, new class implements ForgetsPendingJob
    {
        public function forgetPending(string $id, array $tags): bool
        {
            return true;
        }
    });
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
        return 'queues:'.($queue ?: 'default');
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
