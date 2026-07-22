<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class QueuePauseStateData extends Data
{
    public function __construct(
        public readonly bool $paused,
        public readonly ?int $pausedUntil,
    ) {}
}
