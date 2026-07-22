<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Data;

use Spatie\LaravelData\Data;

final class FailedJobRetryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?float $retriedAt,
    ) {}
}
