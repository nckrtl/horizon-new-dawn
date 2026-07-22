<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Queue\QueueManager;
use NckRtl\HorizonNewDawn\Queues\Data\PauseQueueData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;

final readonly class PauseQueue
{
    public function __construct(
        private QueueManager $queues,
        private QueuePauseMetadata $metadata,
        private ?FrameworkCapabilities $capabilities = null,
    ) {}

    public function handle(PauseQueueData $data): ?CarbonImmutable
    {
        ($this->capabilities ?? FrameworkCapabilities::detect())->ensureQueuePausing();

        if ($data->durationMinutes === null) {
            $this->queues->pause($data->connection, $data->queue);
            $this->metadata->forget($data->connection, $data->queue);

            return null;
        }

        $until = CarbonImmutable::now()->addMinutes($data->durationMinutes);

        $this->queues->pauseFor($data->connection, $data->queue, $until);
        $this->metadata->storeUntil($data->connection, $data->queue, $until);

        return $until;
    }
}
