<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Data;

use Spatie\LaravelData\Data;

final class CancelPendingJobsResultData extends Data
{
    public function __construct(
        public readonly int $cancelled,
        public readonly int $batched,
        public readonly int $failed,
    ) {}
}
