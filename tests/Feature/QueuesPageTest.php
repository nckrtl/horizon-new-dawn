<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionResolverInterface;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Assets\AssetManifest;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Queues\QueueActivityData;
use NckRtl\HorizonNewDawn\Queues\QueueBatchesData;
use NckRtl\HorizonNewDawn\Queues\QueueJobsData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueSummary;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Horizon::auth(static fn (): bool => true);
    config()->set('horizon-new-dawn.poll_interval', 0);

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

function bindFeatureQueueCatalog(?string $name): void
{
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', $name === null
        ? []
        : [(object) ['processes' => ["redis:{$name}" => 2]]]);
    $queues = mockDashboardContract(QueueFactory::class);
    $waits = mockDashboardContract(WaitTimeCalculator::class);
    $metrics = mockDashboardContract(MetricsRepository::class);

    if ($name !== null) {
        $queue = mockDashboardContract(Queue::class);
        dashboardReturnsFor($queue, 'readyNow', [$name], 3);
        dashboardReturnsFor($queue, 'reservedSize', [$name], 1);
        dashboardReturnsFor($queue, 'delayedSize', [$name], 2);
        dashboardReturnsFor($queues, 'connection', ['redis'], $queue);
        dashboardReturnsFor($waits, 'calculateTimeToClear', ['redis', $name, 2], 4);
        dashboardReturnsFor($metrics, 'runtimeForQueue', [$name], 1000);
    }

    app()->instance(
        QueuesData::class,
        new QueuesData(
            $supervisors,
            $queues,
            $waits,
            $metrics,
            app(QueuePauseStatus::class),
            app(QueueWaitThreshold::class),
        ),
    );
}

function bindFeatureQueueDetail(string $queueName): void
{
    $repository = mockDashboardContract(JobRepository::class);
    $pending = horizonJob(0, 'pending-1');
    $pending->queue = $queueName;
    dashboardReturns($repository, 'countPending', 1);
    dashboardReturns($repository, 'getPending', collect([$pending]));
    dashboardReturns($repository, 'countCompleted', 0);
    dashboardReturns($repository, 'countFailed', 0);
    $silenced = horizonJob(0, 'silenced-1');
    $silenced->queue = $queueName;
    dashboardReturns($repository, 'countSilenced', 1);
    dashboardReturns($repository, 'getSilenced', collect([$silenced]));

    $batchRepository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batchRepository, 'get', [50, null], []);
    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturnsFor($metrics, 'throughputForQueue', [$queueName], 0);
    dashboardReturns($metrics, 'snapshotsForQueue', [
        (object) ['time' => 1784588400, 'throughput' => 12, 'runtime' => 1500],
    ]);
    $jobs = new JobsData($repository);
    $batches = new BatchesData(
        $batchRepository,
        new BatchJobsData($repository, $jobs),
        app(ConnectionResolverInterface::class),
    );
    $queueJobs = new QueueJobsData(
        $repository,
        $jobs,
        new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            $jobs,
            new FailedJobRetryEligibility,
        ),
        app(CacheFactory::class),
    );
    $queueBatches = new QueueBatchesData(
        $batchRepository,
        $batches,
        app(CacheFactory::class),
    );

    app()->instance(QueueSummary::class, new QueueSummary(
        $queueJobs,
        $queueBatches,
        $metrics,
    ));
    app()->instance(MetricsData::class, new MetricsData($metrics));
    app()->instance(QueueActivityData::class, new QueueActivityData($queueJobs, $queueBatches));
}

it('renders the supervised queue inventory and honors Horizon authorization', function (): void {
    bindFeatureQueueCatalog('reports');

    get('/horizon/queues')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Queues/Index')
            ->where('meta.title', 'Queues')
            ->where('meta.activeNavigation', 'queues')
            ->where('queues.queues.0.name', 'reports')
            ->where('queues.queues.0.ready', 3));

    Horizon::auth(static fn (): bool => false);
    get('/horizon/queues')->assertForbidden();
});

it('renders an encoded queue detail with the pending activity collection', function (): void {
    bindFeatureQueueCatalog('report exports');
    bindFeatureQueueDetail('report exports');

    get('/horizon/queues/report%20exports')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Queues/Show')
            ->where('meta.title', 'report exports')
            ->where('meta.activeNavigation', 'queues')
            ->where('queue', 'report exports')
            ->where('tab', 'pending')
            ->where('summary.pendingJobs', 1)
            ->where('summary.processes', 2)
            ->where('activity.data.0.id', 'pending-1')
            ->where('activity.complete', true));
});

it('loads queue metric snapshots in the metrics view', function (): void {
    bindFeatureQueueCatalog('reports');
    bindFeatureQueueDetail('reports');

    get('/horizon/queues/reports?view=metrics')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Queues/Show')
            ->where('view', 'metrics')
            ->where('preview.available', true)
            ->where('preview.data.0.timestamp', 1784588400)
            ->where('preview.data.0.throughput', 12)
            ->where('preview.data.0.runtime', 1.5));
});

it('renders queue-filtered silenced activity', function (): void {
    bindFeatureQueueCatalog('reports');
    bindFeatureQueueDetail('reports');

    get('/horizon/queues/reports?tab=silenced')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Queues/Show')
            ->where('tab', 'silenced')
            ->where('summary.silencedJobs', 1)
            ->where('activity.data.0.id', 'silenced-1')
            ->where('activity.complete', true));
});

it('returns 404 for an unknown queue on an initial request', function (): void {
    bindFeatureQueueCatalog(null);

    get('/horizon/queues/missing')->assertNotFound();
});

it('returns unavailable partial props when an open queue is no longer supervised', function (): void {
    bindFeatureQueueCatalog(null);

    getJson('/horizon/queues/reports', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AssetManifest::class)->version(),
        'X-Inertia-Partial-Component' => 'Queues/Show',
        'X-Inertia-Partial-Data' => 'summary,activity,tab',
    ])
        ->assertOk()
        ->assertJsonPath('component', 'Queues/Show')
        ->assertJsonPath('props.summary.available', false)
        ->assertJsonPath('props.activity.available', false)
        ->assertJsonPath('props.tab', 'pending');
});
