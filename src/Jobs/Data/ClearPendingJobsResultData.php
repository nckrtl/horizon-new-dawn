<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Data;

use Spatie\LaravelData\Data;

final class ClearPendingJobsResultData extends Data
{
    /** @param array<int, string> $failedTargets */
    public function __construct(
        public readonly int $cleared,
        public readonly array $failedTargets,
    ) {}
}
