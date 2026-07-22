<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support\Data;

use NckRtl\HorizonNewDawn\Dashboard\HorizonStatus;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use Spatie\LaravelData\Data;

final class HorizonShellData extends Data
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly int $pollInterval,
        public readonly HorizonStatus $status,
        public readonly bool $processing,
        public readonly bool $maintenanceMode,
        public readonly FrameworkCapabilities $capabilities,
    ) {}
}
