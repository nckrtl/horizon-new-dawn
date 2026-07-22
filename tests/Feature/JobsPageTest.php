<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));
});

function bindJobsPageQueueCatalog(int $ready, int $delayed): void
{
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [(object) ['processes' => ['redis:default' => 1]]]);

    $queue = mockDashboardContract(Queue::class);
    dashboardReturnsFor($queue, 'readyNow', ['default'], $ready);
    dashboardReturnsFor($queue, 'reservedSize', ['default'], 0);
    dashboardReturnsFor($queue, 'delayedSize', ['default'], $delayed);
    dashboardReturnsFor($queue, 'creationTimeOfOldestPendingJob', ['default'], null);

    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsFor($queues, 'connection', ['redis'], $queue);

    $waits = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturnsFor($waits, 'calculateTimeToClear', ['redis', 'default', 1], 0);

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturnsFor($metrics, 'runtimeForQueue', ['default'], 0);

    app()->instance(QueuesData::class, new QueuesData(
        $supervisors,
        $queues,
        $waits,
        $metrics,
        app(QueuePauseStatus::class),
        app(QueueWaitThreshold::class),
    ));
}

describe('job pages', function (): void {
    it('provides live pending counts from every relevant queue', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getPending', new Collection([horizonJob(0)]));
        dashboardReturns($repository, 'countPending', 1);
        app()->instance(JobsData::class, new JobsData($repository));
        bindJobsPageQueueCatalog(4, 7);

        get('/horizon/jobs/pending')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Jobs/Index')
                ->where('pendingCounts.available', true)
                ->where('pendingCounts.ready', 4)
                ->where('pendingCounts.delayed', 7));
    });

    it('renders the completed job list with scroll-safe rows', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getCompleted', new Collection([horizonJob(0)]));
        dashboardReturns($repository, 'countCompleted', 1);
        app()->instance(JobsData::class, new JobsData($repository));

        get('/horizon/jobs/completed')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Jobs/Index')
                ->where('meta.title', 'Completed Jobs')
                ->where('meta.activeNavigation', 'completed')
                ->where('type', 'completed')
                ->where('jobs.data.0.id', 'job-1')
                ->where('jobs.total', 1)
                ->missing('jobs.data.0.payload')
                ->missing('jobs.data.0.exception'));
    });

    it('renders a recent job detail and 404s a missing job', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getJobs', new Collection([horizonJob(0)]));
        app()->instance(JobsData::class, new JobsData($repository));

        get('/horizon/jobs/completed/job-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Jobs/Show')
                ->where('meta.title', 'Job Detail')
                ->where('job.id', 'job-1')
                ->where('job.payload.displayName', 'App\\Jobs\\ImportFeed')
                ->missing('job.exception'));

        $missingRepository = mockDashboardContract(JobRepository::class);
        dashboardReturns($missingRepository, 'getJobs', new Collection);
        app()->instance(JobsData::class, new JobsData($missingRepository));
        get('/horizon/jobs/completed/missing')->assertNotFound();
    });
});
