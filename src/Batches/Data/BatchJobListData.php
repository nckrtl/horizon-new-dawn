<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use NckRtl\HorizonNewDawn\Jobs\Data\JobRowData;
use Spatie\LaravelData\Data;

final class BatchJobListData extends Data
{
    /** @param array<int, JobRowData> $rows */
    public function __construct(
        public readonly int $total,
        public readonly array $rows,
        public readonly bool $available,
        public readonly bool $complete,
        public readonly ?string $message,
    ) {}
}
