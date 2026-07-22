<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use Spatie\LaravelData\Data;

final class BatchPageData extends Data
{
    /** @param array<int, BatchRowData> $batches */
    public function __construct(
        public readonly bool $available,
        public readonly array $batches,
        public readonly ?string $current,
        public readonly ?string $next,
        public readonly ?string $message,
    ) {}
}
