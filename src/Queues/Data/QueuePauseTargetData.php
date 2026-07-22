<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class QueuePauseTargetData extends Data
{
    public function __construct(
        public readonly string $connection,
        public readonly bool $paused,
        public readonly ?int $pausedUntil,
        public readonly int $ready,
        public readonly int $reserved,
        public readonly int $delayed,
        public readonly int $total,
    ) {}
}
