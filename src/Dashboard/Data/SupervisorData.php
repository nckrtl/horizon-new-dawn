<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard\Data;

use Spatie\LaravelData\Data;

final class SupervisorData extends Data
{
    /** @param array<int, string> $queues */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $connection,
        public readonly array $queues,
        public readonly int $processes,
        public readonly string $balancing,
        public readonly string $status,
        public readonly ?SupervisorScalingData $scaling,
    ) {}
}
