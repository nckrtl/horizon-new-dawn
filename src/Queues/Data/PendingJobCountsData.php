<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class PendingJobCountsData extends Data
{
    public function __construct(
        public readonly bool $available,
        public readonly ?int $ready,
        public readonly ?int $delayed,
    ) {}
}
