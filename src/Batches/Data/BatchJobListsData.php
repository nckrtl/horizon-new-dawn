<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use Spatie\LaravelData\Data;

final class BatchJobListsData extends Data
{
    public function __construct(
        public readonly BatchJobListData $pending,
        public readonly BatchJobListData $completed,
        public readonly BatchJobListData $failed,
    ) {}
}
