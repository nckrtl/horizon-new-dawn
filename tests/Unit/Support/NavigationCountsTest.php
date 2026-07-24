<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Batches\BatchRepositoryOverview;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Support\NavigationCounts;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsUsing;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('collects bounded navigation counts from Horizon storage', function (): void {
    expect(navigationCounts()->get()->toArray())->toBe([
        'instances' => 2,
        'monitoring' => 2,
        'metrics' => 7,
        'queues' => 3,
        'batches' => 4,
        'pending' => 5,
        'completed' => 36,
        'silenced' => 3,
        'failed' => 6,
    ]);
});

it('isolates a failed monitoring count', function (): void {
    expect(navigationCounts(monitoringFails: true)->get()->toArray())->toBe([
        'instances' => 2,
        'monitoring' => null,
        'metrics' => 7,
        'queues' => 3,
        'batches' => 4,
        'pending' => 5,
        'completed' => 36,
        'silenced' => 3,
        'failed' => 6,
    ]);
});

it('isolates a failed batch count', function (): void {
    expect(navigationCounts(batchFails: true)->get()->toArray())->toBe([
        'instances' => 2,
        'monitoring' => 2,
        'metrics' => 7,
        'queues' => 3,
        'batches' => null,
        'pending' => 5,
        'completed' => 36,
        'silenced' => 3,
        'failed' => 6,
    ]);
});

it('counts batches through the configured repository', function (): void {
    expect(navigationCounts()->get()->batches)->toBe(4);
});

function navigationCounts(
    bool $monitoringFails = false,
    bool $batchFails = false,
): NavigationCounts {
    config()->set('queue.batching.database', null);
    config()->set('horizon-new-dawn.poll_interval', 0);
    $redisConnection = mockDashboardContract(Connection::class);

    if ($monitoringFails) {
        dashboardThrowsFor(
            $redisConnection,
            'scard',
            ['monitoring'],
            new RuntimeException('monitoring unavailable'),
        );
    } else {
        dashboardReturnsFor($redisConnection, 'scard', ['monitoring'], 2);
    }

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

    $batches = mockDashboardContract(BatchRepository::class);

    if ($batchFails) {
        dashboardThrows($batches, 'get', new RuntimeException('batch repository unavailable'));
    } else {
        dashboardReturnsUsing(
            $batches,
            'get',
            static fn (int $limit, ?string $before): array => match ($before) {
                null => [
                    horizonBatch('batch-4'),
                    horizonBatch('batch-3'),
                    horizonBatch('batch-2'),
                    horizonBatch('batch-1'),
                ],
                'batch-1' => [],
                default => throw new LogicException("Unexpected batch cursor [{$before}]."),
            },
        );
    }

    return new NavigationCounts(
        $redis,
        $jobs,
        new BatchRepositoryOverview($batches, app(CacheFactory::class)),
        $queues,
        $masters,
    );
}
