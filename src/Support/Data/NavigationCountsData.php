<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support\Data;

use Spatie\LaravelData\Data;

final class NavigationCountsData extends Data
{
    public function __construct(
        public readonly ?int $instances,
        public readonly ?int $monitoring,
        public readonly ?int $metrics,
        public readonly ?int $queues,
        public readonly ?int $batches,
        public readonly ?int $pending,
        public readonly ?int $completed,
        public readonly ?int $silenced,
        public readonly ?int $failed,
    ) {}
}
