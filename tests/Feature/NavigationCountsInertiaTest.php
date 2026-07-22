<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Redis\Connections\Connection;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Support\NavigationCounts;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;

it('delivers navigation counts in their own deferred group', function (): void {
    config()->set('queue.batching.database', null);
    $redisConnection = mockDashboardContract(Connection::class);
    dashboardReturnsFor($redisConnection, 'scard', ['monitoring'], 2);
    dashboardReturnsFor($redisConnection, 'scard', ['measured_jobs'], 4);
    dashboardReturnsFor($redisConnection, 'scard', ['measured_queues'], 3);
    $redis = mockDashboardContract(RedisFactory::class);
    dashboardReturns($redis, 'connection', $redisConnection);
    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countPending', 5);
    dashboardReturns($jobs, 'countCompleted', 36);
    dashboardReturns($jobs, 'countSilenced', 3);
    dashboardReturns($jobs, 'countFailed', 6);
    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [
        (object) ['name' => 'horizon-web-01'],
        (object) ['name' => 'horizon-worker-01'],
    ]);
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:default,reports' => 2]],
        (object) ['processes' => ['redis:reports,batches' => 1]],
    ]);
    $queues = new QueuesData(
        $supervisors,
        mockDashboardContract(QueueFactory::class),
        mockDashboardContract(WaitTimeCalculator::class),
        mockDashboardContract(MetricsRepository::class),
        app(QueuePauseStatus::class),
        app(QueueWaitThreshold::class),
    );
    $builder = mockDashboardContract(Builder::class);
    dashboardReturns($builder, 'count', 4);
    $connection = mockDashboardContract(ConnectionInterface::class);
    dashboardReturnsFor($connection, 'table', ['job_batches'], $builder);
    $database = mockDashboardContract(ConnectionResolverInterface::class);
    dashboardReturnsFor($database, 'connection', [null], $connection);

    app()->instance(NavigationCounts::class, new NavigationCounts($redis, $jobs, $database, $queues, $masters));

    get('/horizon')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('navigationCounts')
            ->loadDeferredProps('navigation', fn (AssertableInertia $deferred): AssertableInertia => $deferred
                ->where('navigationCounts.instances', 2)
                ->where('navigationCounts.monitoring', 2)
                ->where('navigationCounts.metrics', 7)
                ->where('navigationCounts.queues', 3)
                ->where('navigationCounts.batches', 4)
                ->where('navigationCounts.pending', 5)
                ->where('navigationCounts.completed', 36)
                ->where('navigationCounts.silenced', 3)
                ->where('navigationCounts.failed', 6)));
});
