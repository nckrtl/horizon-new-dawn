<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Data;

use Spatie\LaravelData\Data;

final class FailedJobDetailData extends Data
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  array<int, FailedJobRetryData>  $retriedBy
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $shortName,
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $status,
        public readonly array $tags,
        public readonly ?float $pushedAt,
        public readonly ?float $reservedAt,
        public readonly ?float $failedAt,
        public readonly ?float $runtime,
        public readonly int $attempts,
        public readonly ?string $retryOf,
        public readonly ?int $delay,
        public readonly ?float $delayedUntil,
        public readonly ?string $batchId,
        public readonly bool $retried,
        public readonly array $retriedBy,
        public readonly bool $retryEligible,
        public readonly array $payload,
        public readonly array $context,
        public readonly string $exception,
    ) {}
}
