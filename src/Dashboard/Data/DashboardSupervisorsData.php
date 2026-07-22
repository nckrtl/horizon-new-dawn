<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use Spatie\LaravelData\Data;

final class DashboardSupervisorsData extends Data
{
    /** @param array<int, SupervisorGroupData> $groups */
    public function __construct(
        public readonly bool $available,
        public readonly array $groups,
        public readonly ?string $message,
    ) {}
}
