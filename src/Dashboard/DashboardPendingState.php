<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use LogicException;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardPendingStateData;
use Throwable;

final readonly class DashboardPendingState
{
    public function __construct(private QueueFactory $queues) {}

    /** @param array<string, int|float> $waits */
    public function forQueues(array $waits): DashboardPendingStateData
    {
        try {
            $pairs = [];

            foreach (array_keys($waits) as $supervisedQueue) {
                if (! str_contains($supervisedQueue, ':')) {
                    continue;
                }

                [$connection, $queueNames] = explode(':', $supervisedQueue, 2);

                foreach (explode(',', $queueNames) as $queueName) {
                    $queueName = trim($queueName);

                    if ($connection === '' || $queueName === '') {
                        continue;
                    }

                    $pairs[$connection."\0".$queueName] = [$connection, $queueName];
                }
            }

            $connections = [];
            $reserved = 0;
            $readyNow = 0;
            $delayed = 0;

            foreach ($pairs as [$connection, $queueName]) {
                $queue = $connections[$connection] ??= $this->queues->connection($connection);
                $reserved += $this->count($queue, 'reservedSize', $queueName);
                $readyNow += $this->count($queue, 'readyNow', $queueName);
                $delayed += $this->count($queue, 'delayedSize', $queueName);
            }

            return new DashboardPendingStateData($reserved, $readyNow, $delayed);
        } catch (Throwable $exception) {
            report($exception);

            return new DashboardPendingStateData(null, null, null);
        }
    }

    private function count(object $queue, string $method, string $queueName): int
    {
        $callback = [$queue, $method];

        if (! is_callable($callback)) {
            throw new LogicException("Queue connection does not support {$method}().");
        }

        return (int) $callback($queueName);
    }
}
