<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use NckRtl\HorizonNewDawn\Batches\BatchCreatedRange;
use Spatie\LaravelData\Data;

final class BatchIndexFiltersData extends Data
{
    public function __construct(
        public readonly ?string $query,
        public readonly ?string $queue,
        public readonly ?string $connection,
        public readonly ?BatchCreatedRange $created,
    ) {}
}
