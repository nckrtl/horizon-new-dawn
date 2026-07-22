<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class QueueListData extends Data
{
    /** @param array<int, QueueRowData> $queues */
    public function __construct(
        public readonly bool $available,
        public readonly array $queues,
        public readonly ?string $message,
    ) {}

    public function find(string $name): ?QueueRowData
    {
        foreach ($this->queues as $queue) {
            if ($queue->name === $name) {
                return $queue;
            }
        }

        return null;
    }

    public function pendingCounts(): PendingJobCountsData
    {
        if (! $this->available) {
            return new PendingJobCountsData(false, null, null);
        }

        $ready = 0;
        $delayed = 0;

        foreach ($this->queues as $queue) {
            $ready += $queue->ready;
            $delayed += $queue->delayed;
        }

        return new PendingJobCountsData(true, $ready, $delayed);
    }
}
