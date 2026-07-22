<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use Spatie\LaravelData\Data;

final class BatchDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly string $displayName,
        public readonly int $totalJobs,
        public readonly int $pendingJobs,
        public readonly int $failedJobs,
        public readonly int $failedJobAttempts,
        public readonly int $processedJobs,
        public readonly int $progress,
        public readonly string $status,
        public readonly int $createdAt,
        public readonly ?int $cancelledAt,
        public readonly ?int $finishedAt,
        public readonly ?string $connection,
        public readonly ?string $queue,
        public readonly BatchJobListsData $jobs,
    ) {}
}
