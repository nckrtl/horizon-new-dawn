<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use Spatie\LaravelData\Data;

final class SupervisorGroupData extends Data
{
    /** @param array<int, SupervisorData> $items */
    public function __construct(
        public readonly string $name,
        public readonly ?string $environment,
        public readonly ?int $pid,
        public readonly string $status,
        public readonly bool $local,
        public readonly array $items,
    ) {}
}
