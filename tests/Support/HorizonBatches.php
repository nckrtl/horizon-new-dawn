<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

/** @param array<int, string> $failedJobIds */
function horizonBatch(
    string $id,
    string $name = 'Import customer records',
    int $totalJobs = 100,
    int $pendingJobs = 25,
    int $failedJobs = 0,
    array $failedJobIds = [],
    ?int $cancelledAt = null,
    ?int $finishedAt = null,
    ?BatchRepository $repository = null,
): Batch {
    return new Batch(
        queue: mockDashboardContract(QueueFactory::class),
        repository: $repository ?? mockDashboardContract(BatchRepository::class),
        id: $id,
        name: $name,
        totalJobs: $totalJobs,
        pendingJobs: $pendingJobs,
        failedJobs: $failedJobs,
        failedJobIds: $failedJobIds,
        options: ['connection' => 'redis', 'queue' => 'imports'],
        createdAt: CarbonImmutable::createFromTimestampUTC(1_784_281_000),
        cancelledAt: $cancelledAt === null ? null : CarbonImmutable::createFromTimestampUTC($cancelledAt),
        finishedAt: $finishedAt === null ? null : CarbonImmutable::createFromTimestampUTC($finishedAt),
    );
}
