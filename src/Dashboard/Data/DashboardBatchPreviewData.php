<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use Spatie\LaravelData\Data;

final class DashboardBatchPreviewData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $progress,
    ) {}
}
