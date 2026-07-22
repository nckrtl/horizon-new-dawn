<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use Spatie\LaravelData\Data;

final class DashboardWorkloadData extends Data
{
    /** @param array<int, WorkloadItemData> $items */
    public function __construct(
        public readonly bool $available,
        public readonly array $items,
        public readonly ?string $message,
    ) {}
}
