<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use Spatie\LaravelData\Data;

final class BatchClearCountsData extends Data
{
    public function __construct(
        public readonly int $incomplete,
        public readonly int $complete,
        public readonly int $finished,
        public readonly int $cancelled,
        public readonly bool $available,
    ) {}
}
