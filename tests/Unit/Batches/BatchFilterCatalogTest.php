<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchFilterCatalog;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardExpects;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsUsing;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

afterEach(function (): void {
    app(CacheFactory::class)->store()->clear();
});

it('scans retained batch pages into distinct sorted queue and connection filters', function (): void {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.queue', 'default');

    $recent = horizonBatch('batch-z');
    $recent->options = ['connection' => 'redis', 'queue' => 'imports'];

    $older = horizonBatch('batch-y');
    $older->options = ['connection' => 'database', 'queue' => 'reports'];

    $duplicates = horizonBatch('batch-x');
    $duplicates->options = ['connection' => 'redis', 'queue' => 'imports'];

    $nulls = horizonBatch('batch-w');
    $nulls->options = ['connection' => '', 'queue' => ''];

    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [$recent],
            'batch-z' => [$older, $duplicates, $nulls],
            'batch-w' => [],
            default => throw new LogicException("Unexpected batch cursor [{$before}]."),
        },
    );

    $catalog = (new BatchFilterCatalog(
        $repository,
        catalogBatchData($repository),
        app(CacheFactory::class),
    ))->get();

    expect($catalog->available)->toBeTrue()
        ->and($catalog->queues)->toBe(['default', 'imports', 'reports'])
        ->and($catalog->connections)->toBe(['database', 'redis']);
});

it('excludes null connections while keeping the default queue fallback', function (): void {
    config()->set('queue.default', null);
    config()->set('queue.connections', []);

    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [tap(horizonBatch('batch-z'), function ($batch): void {
                $batch->options = ['connection' => '', 'queue' => ''];
            })],
            'batch-z' => [],
            default => throw new LogicException("Unexpected batch cursor [{$before}]."),
        },
    );

    $catalog = (new BatchFilterCatalog(
        $repository,
        catalogBatchData($repository),
        app(CacheFactory::class),
    ))->get();

    expect($catalog->available)->toBeTrue()
        ->and($catalog->queues)->toBe(['default'])
        ->and($catalog->connections)->toBe([]);
});

it('returns an unavailable catalog when the repository repeats its final cursor', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [horizonBatch('batch-z')],
            'batch-z' => [horizonBatch('batch-z')],
            default => [],
        },
    );

    $catalog = (new BatchFilterCatalog(
        $repository,
        catalogBatchData($repository),
        app(CacheFactory::class),
    ))->get();

    expect($catalog->available)->toBeFalse()
        ->and($catalog->queues)->toBe([])
        ->and($catalog->connections)->toBe([]);
});

it('returns an unavailable catalog when the repository ends a page with a blank cursor', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [horizonBatch('batch-z'), horizonBatch('')],
            default => [],
        },
    );

    $catalog = (new BatchFilterCatalog(
        $repository,
        catalogBatchData($repository),
        app(CacheFactory::class),
    ))->get();

    expect($catalog->available)->toBeFalse()
        ->and($catalog->queues)->toBe([])
        ->and($catalog->connections)->toBe([]);
});

it('returns an unavailable catalog when the repository cursor increases on a later page', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [horizonBatch('batch-z'), horizonBatch('batch-y')],
            'batch-y' => [horizonBatch('batch-z')],
            default => [],
        },
    );

    $catalog = (new BatchFilterCatalog(
        $repository,
        catalogBatchData($repository),
        app(CacheFactory::class),
    ))->get();

    expect($catalog->available)->toBeFalse()
        ->and($catalog->queues)->toBe([])
        ->and($catalog->connections)->toBe([]);
});

it('uses a one-second cache ttl for a 1500ms poll interval', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 1500);

    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [tap(horizonBatch('batch-z'), function ($batch): void {
                $batch->options = ['connection' => 'redis', 'queue' => 'imports'];
            })],
            'batch-z' => [],
            default => throw new LogicException("Unexpected batch cursor [{$before}]."),
        },
    );

    $store = mockDashboardContract(CacheRepository::class);
    dashboardExpects(
        $store,
        'remember',
        [
            'horizon-new-dawn:batch-filter-catalog:v1',
            1,
            Mockery::on(static fn (mixed $value): bool => $value instanceof Closure),
        ],
        'once',
        returnUsing: static fn (string $key, int $seconds, Closure $callback): array => $callback(),
    );

    $cache = mockDashboardContract(CacheFactory::class);
    dashboardExpects($cache, 'store', times: 'once', value: $store);

    $catalog = new BatchFilterCatalog($repository, catalogBatchData($repository), $cache);

    expect($catalog->get()->toArray())->toBe([
        'available' => true,
        'queues' => ['imports'],
        'connections' => ['redis'],
    ]);
});

it('shares a normalized cached catalog for one poll interval and rebuilds invalid payloads', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 5000);

    $repository = mockDashboardContract(BatchRepository::class);
    $calls = 0;
    dashboardReturnsUsing(
        $repository,
        'get',
        static function (int $limit, ?string $before) use (&$calls): array {
            $calls++;

            return match ($before) {
                null => [tap(horizonBatch('batch-z'), function ($batch): void {
                    $batch->options = ['connection' => 'redis', 'queue' => 'imports'];
                })],
                'batch-z' => [],
                default => throw new LogicException("Unexpected batch cursor [{$before}]."),
            };
        },
    );

    $cache = app(CacheFactory::class)->store();
    $first = (new BatchFilterCatalog($repository, catalogBatchData($repository), app(CacheFactory::class)))->get();
    $second = (new BatchFilterCatalog($repository, catalogBatchData($repository), app(CacheFactory::class)))->get();

    $cache->put('horizon-new-dawn:batch-filter-catalog:v1', [
        'available' => true,
        'queues' => ['imports', 123],
        'connections' => ['redis'],
    ], 5);

    $third = (new BatchFilterCatalog($repository, catalogBatchData($repository), app(CacheFactory::class)))->get();

    expect($first->toArray())->toBe([
        'available' => true,
        'queues' => ['imports'],
        'connections' => ['redis'],
    ])->and($second->toArray())->toBe($first->toArray())
        ->and($third->toArray())->toBe($first->toArray())
        ->and($calls)->toBe(4);
});

it('falls back to a repository-built catalog when the cache store fails', function (): void {
    $repository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [tap(horizonBatch('batch-z'), function ($batch): void {
                $batch->options = ['connection' => 'redis', 'queue' => 'imports'];
            })],
            'batch-z' => [],
            default => throw new LogicException("Unexpected batch cursor [{$before}]."),
        },
    );

    $failingStore = mockDashboardContract(CacheRepository::class);
    dashboardExpects($failingStore, 'remember', times: 'never');
    $cache = mockDashboardContract(CacheFactory::class);
    dashboardExpects(
        $cache,
        'store',
        times: 'once',
        exception: new RuntimeException('cache store unavailable'),
    );

    $catalog = (new BatchFilterCatalog(
        $repository,
        catalogBatchData($repository),
        $cache,
    ))->get();

    expect($catalog->toArray())->toBe([
        'available' => true,
        'queues' => ['imports'],
        'connections' => ['redis'],
    ]);
});

function catalogBatchData(BatchRepository $repository): BatchesData
{
    $jobs = mockDashboardContract(JobRepository::class);

    return new BatchesData(
        $repository,
        new BatchJobsData($jobs, new JobsData($jobs)),
    );
}
