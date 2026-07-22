<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Illuminate\Queue\QueueManager;
use NckRtl\HorizonNewDawn\Queues\Data\QueuePauseStateData;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;

final readonly class QueuePauseStatus
{
    public function __construct(
        private QueueManager $queues,
        private QueuePauseMetadata $metadata,
        private ?FrameworkCapabilities $capabilities = null,
    ) {}

    public function for(string $connection, string $queue): QueuePauseStateData
    {
        if (! ($this->capabilities ?? FrameworkCapabilities::detect())->queuePausing) {
            $this->metadata->forget($connection, $queue);

            return new QueuePauseStateData(paused: false, pausedUntil: null);
        }

        if (! $this->queues->isPaused($connection, $queue)) {
            $this->metadata->forget($connection, $queue);

            return new QueuePauseStateData(paused: false, pausedUntil: null);
        }

        return new QueuePauseStateData(
            paused: true,
            pausedUntil: $this->metadata->pausedUntil($connection, $queue),
        );
    }
}
