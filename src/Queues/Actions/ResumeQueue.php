<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Actions;

use Illuminate\Queue\QueueManager;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;

final readonly class ResumeQueue
{
    public function __construct(
        private QueueManager $queues,
        private QueuePauseMetadata $metadata,
        private ?FrameworkCapabilities $capabilities = null,
    ) {}

    public function handle(string $connection, string $queue): void
    {
        ($this->capabilities ?? FrameworkCapabilities::detect())->ensureQueuePausing();

        $this->queues->resume($connection, $queue);
        $this->metadata->forget($connection, $queue);
    }
}
