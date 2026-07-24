<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchPreviewData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRetainedBatchesData;
use NckRtl\HorizonNewDawn\Queues\QueueBatchesData;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardExpects;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

function retainedQueueBatchesData(BatchRepository $repository): QueueBatchesData
{
    $jobs = mockDashboardContract(JobRepository::class);
    $batches = new BatchesData(
        $repository,
        new BatchJobsData($jobs, new JobsData($jobs)),
    );

    return new QueueBatchesData($repository, $batches, app(CacheFactory::class));
}

function retainedQueueBatch(
    string $id,
    ?string $queue,
    int $pending = 5,
    int $failed = 0,
    ?int $cancelledAt = null,
): Batch {
    $batch = horizonBatch(
        $id,
        name: "Batch {$id}",
        totalJobs: 10,
        pendingJobs: $pending,
        failedJobs: $failed,
        cancelledAt: $cancelledAt,
    );

    if ($queue === null) {
        unset($batch->options['queue']);
    } else {
        $batch->options['queue'] = $queue;
    }

    return $batch;
}

function retainedBatchId(int $index): string
{
    return sprintf('batch-%03d', $index);
}

beforeEach(function (): void {
    app(CacheFactory::class)->store()->clear();
    config()->set('horizon-new-dawn.poll_interval', 0);
    config()->set('horizon.prefix', 'queue-batch-tests:');
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.queue', 'default');
});

it('includes implicitly attributed batches in queue pages and summaries', function (): void {
    config()->set('queue.connections.redis.queue', 'reports');

    $repository = mockDashboardContract(BatchRepository::class);
    $implicit = retainedQueueBatch('implicit', null, pending: 4);
    dashboardReturnsFor($repository, 'get', [50, null], [$implicit]);
    dashboardReturnsFor($repository, 'get', [50, 'implicit'], []);
    dashboardReturnsFor($repository, 'get', [50, null], [$implicit]);
    dashboardReturnsFor($repository, 'get', [50, 'implicit'], []);

    $data = retainedQueueBatchesData($repository);
    $summary = $data->summary('reports');
    $page = $data->page('reports', null);

    expect($summary->total)->toBe(1)
        ->and($page->rows)->toHaveCount(1)
        ->and($page->rows[0]->queue)->toBe('reports');
});

it('filters retained batches and counts only genuinely active matching batches', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    $source = [
        retainedQueueBatch('active-2', 'reports', pending: 4),
        retainedQueueBatch('active-1', 'reports', pending: 3, failed: 1),
        retainedQueueBatch('failed-only', 'reports', pending: 1, failed: 1),
        retainedQueueBatch('cancelled', 'reports', pending: 4, cancelledAt: 1_784_281_050),
        retainedQueueBatch('finished', 'reports', pending: 0),
        retainedQueueBatch('other', 'other'),
        retainedQueueBatch('unattributed', null),
    ];
    dashboardReturnsFor($repository, 'get', [50, null], $source);
    dashboardReturnsFor($repository, 'get', [50, 'unattributed'], []);
    dashboardReturnsFor($repository, 'get', [50, null], $source);
    dashboardReturnsFor($repository, 'get', [50, 'unattributed'], []);

    $data = retainedQueueBatchesData($repository);
    $summary = $data->summary('reports');
    $page = $data->page('reports', null);

    expect($summary->total)->toBe(5)
        ->and($summary->active)->toBe(2)
        ->and(array_column($summary->toArray()['previews'], 'id'))->toBe(['active-2', 'active-1'])
        ->and($summary->complete)->toBeTrue()
        ->and(array_column($page->toArray()['rows'], 'id'))->toBe([
            'active-2',
            'active-1',
            'failed-only',
            'cancelled',
            'finished',
        ])
        ->and($page->complete)->toBeTrue();
});

it('replaces unserializable legacy summary objects with scalar cache payloads', function (): void {
    requireConfigurableCacheUnserialization();

    config()->set('cache.stores.array.serialize', true);
    config()->set('cache.serializable_classes', false);
    config()->set('horizon-new-dawn.poll_interval', 5_000);
    app(CacheManager::class)->purge();

    $cache = app(CacheFactory::class)->store();
    $cacheKey = 'horizon-new-dawn:queue-batches:'.hash(
        'sha256',
        "queue-batch-tests:\0reports",
    );
    $cache->put($cacheKey, new QueueRetainedBatchesData(
        total: 99,
        active: 99,
        previews: [new DashboardBatchPreviewData('legacy', 'Legacy', 99)],
        complete: true,
        message: null,
    ), 60);

    expect($cache->get($cacheKey))->toBeInstanceOf(__PHP_Incomplete_Class::class);

    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($repository, 'get', [50, null], [
        retainedQueueBatch('active', 'reports', pending: 4),
    ]);
    dashboardReturnsFor($repository, 'get', [50, 'active'], []);

    $data = retainedQueueBatchesData($repository);
    $first = $data->summary('reports');
    $cached = $data->summary('reports');

    expect($first->total)->toBe(1)
        ->and($cached->total)->toBe(1)
        ->and($cached->previews)->toHaveCount(1)
        ->and($cached->previews[0]->id)->toBe('active')
        ->and($cache->get($cacheKey))->toBeArray();
});

it('rounds a 1500ms retained batch summary poll interval down to a one second cache ttl', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 1500);

    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($repository, 'get', [50, null], [
        retainedQueueBatch('active', 'reports', pending: 4),
    ]);
    dashboardReturnsFor($repository, 'get', [50, 'active'], []);

    $cache = mockDashboardContract(CacheRepository::class);
    $factory = mockDashboardContract(CacheFactory::class);
    dashboardExpects($factory, 'store', times: 'twice', value: $cache);
    dashboardExpects(
        $cache,
        'remember',
        [
            Mockery::type('string'),
            1,
            Mockery::type(Closure::class),
        ],
        'once',
        returnUsing: static fn (string $key, int $seconds, Closure $callback): array => $callback(),
    );

    $jobs = mockDashboardContract(JobRepository::class);
    $data = new QueueBatchesData(
        $repository,
        new BatchesData($repository, new BatchJobsData($jobs, new JobsData($jobs))),
        $factory,
    );

    expect($data->summary('reports')->total)->toBe(1);
});

it('continues from the final inspected batch after a bounded nonmatching scan', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);

    foreach (range(0, 4) as $page) {
        $highest = 250 - ($page * 50);
        $lowest = $highest - 49;
        $before = $page === 0 ? null : retainedBatchId($highest + 1);
        dashboardReturnsFor($repository, 'get', [50, $before], array_map(
            static fn (int $index): Batch => retainedQueueBatch(retainedBatchId($index), 'other'),
            range($highest, $lowest),
        ));
    }

    $page = retainedQueueBatchesData($repository)->page('reports', null);

    expect($page->rows)->toBe([])
        ->and($page->total)->toBe(0)
        ->and($page->complete)->toBeFalse()
        ->and($page->next)->toBe('batch-051')
        ->and($page->message)->toBe('More retained entries may exist for this queue.');
});

it('caps queue batch pages at the 50th matching batch and uses that batch id as the continuation cursor', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    $firstPage = array_map(
        static fn (int $index): Batch => retainedQueueBatch(retainedBatchId($index), 'reports'),
        range(200, 152),
    );
    $firstPage[] = retainedQueueBatch(retainedBatchId(151), 'other');
    $secondPage = [
        retainedQueueBatch(retainedBatchId(150), 'reports'),
        retainedQueueBatch(retainedBatchId(149), 'reports'),
        retainedQueueBatch(retainedBatchId(148), 'other'),
    ];
    dashboardReturnsFor($repository, 'get', [50, null], $firstPage);
    dashboardReturnsFor($repository, 'get', [50, retainedBatchId(151)], $secondPage);

    $page = retainedQueueBatchesData($repository)->page('reports', null);

    expect($page->rows)->toHaveCount(50)
        ->and($page->next)->toBe('batch-150')
        ->and($page->complete)->toBeFalse()
        ->and($page->message)->toBeNull()
        ->and($page->rows[48]->id)->toBe('batch-152')
        ->and($page->rows[49]->id)->toBe('batch-150');
});

it('marks a capped retained batch summary as incomplete', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);

    foreach (range(0, 4) as $page) {
        $highest = 250 - ($page * 50);
        $lowest = $highest - 49;
        $before = $page === 0 ? null : retainedBatchId($highest + 1);
        dashboardReturnsFor($repository, 'get', [50, $before], array_map(
            static fn (int $index): Batch => retainedQueueBatch(retainedBatchId($index), 'reports'),
            range($highest, $lowest),
        ));
    }

    $summary = retainedQueueBatchesData($repository)->summary('reports');

    expect($summary->total)->toBe(250)
        ->and($summary->complete)->toBeFalse()
        ->and($summary->previews)->toHaveCount(3);
});

it('bypasses retained batch summary caching for subsecond poll intervals', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 999);

    $repository = mockDashboardContract(BatchRepository::class);

    foreach (range(1, 2) as $_) {
        dashboardReturnsFor($repository, 'get', [50, null], [
            retainedQueueBatch('active', 'reports', pending: 4),
        ]);
        dashboardReturnsFor($repository, 'get', [50, 'active'], []);
    }

    $factory = mockDashboardContract(CacheFactory::class);
    dashboardExpects($factory, 'store', times: 'never');

    $jobs = mockDashboardContract(JobRepository::class);
    $data = new QueueBatchesData(
        $repository,
        new BatchesData($repository, new BatchJobsData($jobs, new JobsData($jobs))),
        $factory,
    );

    expect($data->summary('reports')->total)->toBe(1)
        ->and($data->summary('reports')->total)->toBe(1);
});

it('isolates batch repository failures behind safe states', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    dashboardThrowsFor($repository, 'get', [50, null], new RuntimeException('database secret'));

    $page = retainedQueueBatchesData($repository)->page('reports', null);

    expect($page->available)->toBeFalse()
        ->and($page->rows)->toBe([])
        ->and($page->message)->toBe('Retained batches are currently unavailable.')
        ->and($page->message)->not->toContain('secret');
});
