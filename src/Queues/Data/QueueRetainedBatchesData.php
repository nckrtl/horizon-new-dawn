<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchPreviewData;
use Spatie\LaravelData\Data;

final class QueueRetainedBatchesData extends Data
{
    /** @param array<int, DashboardBatchPreviewData> $previews */
    public function __construct(
        public readonly int $total,
        public readonly int $active,
        public readonly array $previews,
        public readonly bool $complete,
        public readonly ?string $message,
    ) {}
}
