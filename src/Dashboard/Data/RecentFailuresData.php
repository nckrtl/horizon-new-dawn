<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use Spatie\LaravelData\Data;

final class RecentFailuresData extends Data
{
    /** @param array<int, FailurePreviewData> $items */
    public function __construct(
        public readonly bool $available,
        public readonly array $items,
        public readonly ?string $message,
    ) {}
}
