<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class PauseQueueData extends Data
{
    public function __construct(
        public readonly string $connection,
        public readonly string $queue,
        public readonly ?int $durationMinutes,
    ) {}
}
