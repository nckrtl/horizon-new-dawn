<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use NckRtl\HorizonNewDawn\Batches\BatchRepositoryOverview;

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
