<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Data;

use Spatie\LaravelData\Data;

final class JobPageData extends Data
{
    /** @param array<int, JobRowData> $items */
    public function __construct(
        public readonly bool $available,
        public readonly array $items,
        public readonly int $total,
        public readonly int|string|null $current,
        public readonly int|string|null $next,
        public readonly ?string $message,
    ) {}
}
