<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Data;

use Spatie\LaravelData\Data;

final class JobRowData extends Data
{
    /** @param array<int, string> $tags */
    public function __construct(
        public readonly string $id,
        public readonly int $index,
        public readonly string $name,
        public readonly string $shortName,
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $status,
        public readonly array $tags,
        public readonly int $attempts,
        public readonly ?string $retryOf,
        public readonly ?int $delay,
        public readonly ?float $pushedAt,
        public readonly ?float $reservedAt,
        public readonly ?float $completedAt,
        public readonly ?float $failedAt,
        public readonly ?float $runtime,
        public readonly ?float $occurredAt,
        public readonly bool $retried,
        public readonly bool $retryCompleted,
        public readonly int $retryCount,
        public readonly ?string $latestRetryStatus,
        public readonly bool $retryEligible,
    ) {}
}
