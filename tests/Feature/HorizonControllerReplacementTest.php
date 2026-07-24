<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Http\Controllers\HomeController as HorizonHomeController;
use Laravel\Horizon\Http\Controllers\MonitoringController as HorizonMonitoringController;
use Laravel\Horizon\Http\Middleware\Authenticate;
use Laravel\Horizon\Jobs\MonitorTag as HorizonMonitorTag;
use Laravel\Horizon\Jobs\StopMonitoringTag as HorizonStopMonitoringTag;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Batches\BatchRepositoryOverview;
use NckRtl\HorizonNewDawn\Dashboard\DashboardBatchSummary;
use NckRtl\HorizonNewDawn\Dashboard\DashboardData;
use NckRtl\HorizonNewDawn\Dashboard\DashboardPendingState;
use NckRtl\HorizonNewDawn\Http\Controllers\HomeController;
use NckRtl\HorizonNewDawn\Http\Controllers\MonitoringApiController;
use NckRtl\HorizonNewDawn\Http\Middleware\HandleInertiaRequests;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

describe('Horizon controller replacement', function (): void {
    it('keeps the Horizon catch-all as an authenticated fallback', function (): void {
        expect(app(HorizonHomeController::class))->toBeInstanceOf(HomeController::class);

        $route = Route::getRoutes()->getByName('horizon.index');

        expect($route)->not->toBeNull()
            ->and($route?->gatherMiddleware())->not->toContain(HandleInertiaRequests::class)
            ->and($route?->gatherMiddleware())->toContain(Authenticate::class);
    });

    it('guards mutations on the preserved Horizon monitoring API', function (): void {
        withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
        Horizon::auth(static fn (): bool => true);
        Bus::fake();

        $jobs = mockDashboardContract(JobRepository::class);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['checkout']);
        app()->instance(JobRepository::class, $jobs);
        app()->instance(TagRepository::class, $tags);

        expect(app(HorizonMonitoringController::class))
            ->toBeInstanceOf(MonitoringApiController::class);

        postJson('/horizon/api/monitoring', ['tag' => 'pending_jobs'])
            ->assertUnprocessable();
        deleteJson('/horizon/api/monitoring/pending_jobs')
            ->assertUnprocessable();
        deleteJson('/horizon/api/monitoring/not-monitored')
            ->assertUnprocessable();

        postJson('/horizon/api/monitoring', ['tag' => 'checkout'])
            ->assertOk();
        deleteJson('/horizon/api/monitoring/checkout')
            ->assertOk();

        Bus::assertDispatched(
            HorizonMonitorTag::class,
            fn (HorizonMonitorTag $job): bool => $job->tag === 'checkout',
        );
        Bus::assertDispatched(
            HorizonStopMonitoringTag::class,
            fn (HorizonStopMonitoringTag $job): bool => $job->tag === 'checkout',
        );
        Bus::assertDispatchedTimes(HorizonMonitorTag::class, 1);
        Bus::assertDispatchedTimes(HorizonStopMonitoringTag::class, 1);
    });

    it('returns the New Dawn dashboard from the concrete package route', function (): void {
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturns($jobs, 'countFailed', 3);
        dashboardReturns($jobs, 'countCompleted', 36);
        dashboardReturns($jobs, 'countPending', 5);

        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturns($metrics, 'measuredQueues', ['default']);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturns($supervisors, 'all', [
            (object) [
                'name' => 'horizon-web-01:supervisor-1',
                'master' => 'horizon-web-01',
                'status' => 'running',
                'processes' => ['redis:default' => 3],
                'options' => ['connection' => 'redis', 'balance' => 'auto'],
            ],
        ]);

        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardReturns($masters, 'all', [(object) ['name' => 'horizon-web-01', 'status' => 'running']]);

        $workload = mockDashboardContract(WorkloadRepository::class);
        dashboardReturns($workload, 'get', [
            ['name' => 'default', 'length' => 8, 'wait' => 4, 'processes' => 3, 'split_queues' => null],
        ]);

        $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
        dashboardReturns($waitTimes, 'calculate', ['redis:default' => 4]);

        $queue = mockDashboardContract(Queue::class);
        dashboardReturns($queue, 'reservedSize', 1);
        dashboardReturns($queue, 'readyNow', 8);
        dashboardReturns($queue, 'delayedSize', 0);
        $queues = mockDashboardContract(QueueFactory::class);
        dashboardReturns($queues, 'connection', $queue);

        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'get', [100, null], []);

        $connection = mockDashboardContract(Connection::class);
        dashboardReturns($connection, 'zcount', 0);
        dashboardReturnsFor(
            $connection,
            'zrange',
            ['snapshot:queue:default', -1, -1],
            [json_encode(['runtime' => 1_100, 'throughput' => 80], JSON_THROW_ON_ERROR)],
        );
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturns($redis, 'connection', $connection);
        $pendingState = new DashboardPendingState($queues);
        $queueManager = app(QueueManager::class);

        if (queuePausingIsSupported()) {
            $queueManager->resume('redis', 'default');
        }

        app()->instance(DashboardData::class, new DashboardData(
            $jobs,
            $metrics,
            $supervisors,
            $masters,
            $queues,
            $waitTimes,
            new QueuePauseStatus($queueManager, new QueuePauseMetadata(app('cache'))),
            $pendingState,
            new DashboardBatchSummary(new BatchRepositoryOverview(
                $batches,
                app(CacheFactory::class),
            )),
            $redis,
            app(QueueWaitThreshold::class),
        ));
        app()->instance(
            HorizonRuntime::class,
            new HorizonRuntime($masters, $workload, $pendingState, $waitTimes),
        );

        get('/horizon')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Dashboard')
                ->where('meta.title', 'Dashboard')
                ->where('meta.activeNavigation', 'dashboard')
                ->where('horizon.baseUrl', url('/horizon'))
                ->where('horizon.pollInterval', 5000)
                ->where('horizon.status', 'running')
                ->where('horizon.processing', true)
                ->where('horizon.maintenanceMode', false)
                ->where('summary.available', true)
                ->where('summary.status', 'running')
                ->where('summary.pendingJobs', 5)
                ->where('summary.failedJobs', 3)
                ->where('summary.completedJobs', 36)
                ->where('workload.available', true)
                ->where('workload.items.0.name', 'default')
                ->where('supervisors.available', true)
                ->where('supervisors.groups.0.name', 'horizon-web-01'));

        get('/horizon/instances')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Instances/Index')
                ->where('meta.title', 'Instances')
                ->where('meta.activeNavigation', 'instances')
                ->where('supervisors.available', true)
                ->where('supervisors.groups.0.name', 'horizon-web-01'));
    });

    it('does not claim unsupported Horizon screens', function (): void {
        get('/horizon/jobs')->assertNotFound();
    });

    it('shares unavailable framework capabilities with the interface', function (): void {
        app()->instance(FrameworkCapabilities::class, new FrameworkCapabilities(queuePausing: false));

        get('/horizon')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('horizon.capabilities.queuePausing', false));
    });
});
