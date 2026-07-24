<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use NckRtl\HorizonNewDawn\Batches\BatchRepositoryOverview;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardExpects;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsUsing;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

afterEach(function (): void {
    app(CacheFactory::class)->store()->clear();
});

it('shares one repository scan for batch counts and previews during a poll interval', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 5000);
    app(CacheFactory::class)->store()->clear();

    $active = horizonBatch(
        'batch-2',
        name: 'Active import',
        totalJobs: 4,
        pendingJobs: 2,
    );
    $failedOnly = horizonBatch(
        'batch-1',
        totalJobs: 2,
        pendingJobs: 1,
        failedJobs: 1,
    );
    $repository = mockDashboardContract(BatchRepository::class);
    $repositoryCalls = 0;
    dashboardReturnsUsing(
        $repository,
        'get',
        static function (int $limit, ?string $before) use ($active, $failedOnly, &$repositoryCalls): array {
            $repositoryCalls++;

            return match ($before) {
                null => [$active, $failedOnly],
                'batch-1' => [],
                default => throw new LogicException("Unexpected batch cursor [{$before}]."),
            };
        },
    );

    $first = (new BatchRepositoryOverview(
        $repository,
        app(CacheFactory::class),
    ))->get();
    $second = (new BatchRepositoryOverview(
        $repository,
        app(CacheFactory::class),
    ))->get();

    expect($first)->toBe([
        'total' => 2,
        'active' => 1,
        'previews' => [
            ['id' => 'batch-2', 'name' => 'Active import', 'progress' => 50],
        ],
    ])->and($second)->toBe($first)
        ->and($repositoryCalls)->toBe(2);
});

it('rounds a 1500ms poll interval down to a one second cache ttl', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 1500);

    $repository = mockDashboardContract(BatchRepository::class);
    $cache = mockDashboardContract(CacheRepository::class);
    $factory = mockDashboardContract(CacheFactory::class);
    $active = horizonBatch(
        'batch-2',
        name: 'Active import',
        totalJobs: 4,
        pendingJobs: 2,
    );

    dashboardReturnsUsing(
        $repository,
        'get',
        static fn (int $limit, ?string $before): array => match ($before) {
            null => [$active],
            'batch-2' => [],
            default => throw new LogicException("Unexpected batch cursor [{$before}]."),
        },
    );

    dashboardExpects($factory, 'store', times: 'once', value: $cache);
    dashboardExpects(
        $cache,
        'remember',
        [
            'horizon-new-dawn:batch-repository-overview:v1',
            1,
            Mockery::type(Closure::class),
        ],
        'once',
        returnUsing: static fn (string $key, int $seconds, Closure $callback): array => $callback(),
    );

    $overview = new BatchRepositoryOverview($repository, $factory);

    expect($overview->get())->toBe([
        'total' => 1,
        'active' => 1,
        'previews' => [
            ['id' => 'batch-2', 'name' => 'Active import', 'progress' => 50],
        ],
    ]);
});

it('bypasses batch repository overview caching for subsecond poll intervals', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 999);

    $active = horizonBatch(
        'batch-2',
        name: 'Active import',
        totalJobs: 4,
        pendingJobs: 2,
    );
    $repository = mockDashboardContract(BatchRepository::class);
    $repositoryCalls = 0;
    dashboardReturnsUsing(
        $repository,
        'get',
        static function (int $limit, ?string $before) use ($active, &$repositoryCalls): array {
            $repositoryCalls++;

            return match ($before) {
                null => [$active],
                'batch-2' => [],
                default => throw new LogicException("Unexpected batch cursor [{$before}]."),
            };
        },
    );

    $factory = mockDashboardContract(CacheFactory::class);
    dashboardExpects($factory, 'store', times: 'never');

    $overview = new BatchRepositoryOverview($repository, $factory);

    expect($overview->get()['total'])->toBe(1)
        ->and($overview->get()['total'])->toBe(1)
        ->and($repositoryCalls)->toBe(4);
});
