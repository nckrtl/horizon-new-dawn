<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Data;

use Spatie\LaravelData\Data;

final class MonitoringPageData extends Data
{
    /** @param array<int, MonitoredTagData> $tags */
    public function __construct(
        public readonly bool $available,
        public readonly array $tags,
        public readonly ?string $message,
    ) {}
}
