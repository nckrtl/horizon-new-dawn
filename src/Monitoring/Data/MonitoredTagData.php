<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Data;

use Spatie\LaravelData\Data;

final class MonitoredTagData extends Data
{
    public function __construct(
        public readonly string $tag,
        public readonly int $trackedCount,
        public readonly int $failedCount,
        public readonly ?float $lastActivityAt,
        public readonly bool $silenced,
    ) {}
}
