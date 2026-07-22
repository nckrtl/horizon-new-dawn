<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Queues\Data\QueueListData;
use NckRtl\HorizonNewDawn\Queues\Data\QueuePauseTargetData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRowData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueTargetData;
use Throwable;

final readonly class QueuesData
{
    public function __construct(
        private SupervisorRepository $supervisors,
        private QueueFactory $queues,
        private WaitTimeCalculator $waitTimes,
        private MetricsRepository $metrics,
        private QueuePauseStatus $pauseStatus,
        private QueueWaitThreshold $waitThreshold,
    ) {}

    public function all(): QueueListData
    {
        try {
            $processes = $this->processesByQueue();
            $connections = [];
            $rows = [];

            foreach ($processes as $queueName => $queueConnections) {
                $ready = 0;
                $reserved = 0;
                $delayed = 0;
                $processCount = 0;
                $wait = 0;
                $waitThresholdTargets = [];
                $targetCounts = [];

                foreach ($queueConnections as $connection => $connectionProcesses) {
                    $queue = $connections[$connection] ??= $this->queues->connection($connection);
                    $targetReady = (int) $queue->readyNow($queueName);
                    $targetReserved = (int) $queue->reservedSize($queueName);
                    $targetDelayed = (int) $queue->delayedSize($queueName);
                    $ready += $targetReady;
                    $reserved += $targetReserved;
                    $delayed += $targetDelayed;
                    $processCount += $connectionProcesses;
                    $targetCounts[$connection] = [
                        'ready' => $targetReady,
                        'reserved' => $targetReserved,
                        'delayed' => $targetDelayed,
                    ];
                    $targetWait = $this->waitTimes->calculateTimeToClear(
                        $connection,
                        $queueName,
                        $connectionProcesses,
                    );
                    $wait = max($wait, $targetWait);
                    $waitThresholdTargets[] = $this->waitThreshold->forTarget(
                        connection: $connection,
                        queue: $queueName,
                        waitSeconds: $this->canCalculateWait($queueName, $targetReady)
                            ? $targetWait
                            : null,
                        oldestPendingAt: $this->oldestPendingAt($queue, $queueName),
                    );
                }

                $connectionNames = array_keys($queueConnections);
                sort($connectionNames, SORT_NATURAL);

                $rows[] = new QueueRowData(
                    name: $queueName,
                    connections: $connectionNames,
                    pauseTargets: array_map(function (string $connection) use ($queueName, $targetCounts): QueuePauseTargetData {
                        $state = $this->pauseStatus->for($connection, $queueName);
                        $counts = $targetCounts[$connection];

                        return new QueuePauseTargetData(
                            connection: $connection,
                            paused: $state->paused,
                            pausedUntil: $state->pausedUntil,
                            ready: $counts['ready'],
                            reserved: $counts['reserved'],
                            delayed: $counts['delayed'],
                            total: $counts['ready'] + $counts['reserved'] + $counts['delayed'],
                        );
                    }, $connectionNames),
                    ready: $ready,
                    reserved: $reserved,
                    delayed: $delayed,
                    processes: $processCount,
                    wait: $wait,
                    waitThreshold: $this->waitThreshold->summarize($waitThresholdTargets),
                );
            }

            usort(
                $rows,
                static fn (QueueRowData $left, QueueRowData $right): int => strnatcasecmp($left->name, $right->name),
            );

            return new QueueListData(true, $rows, null);
        } catch (Throwable $exception) {
            report($exception);

            return new QueueListData(false, [], 'Horizon queues are currently unavailable.');
        }
    }

    public function count(): int
    {
        return count($this->processesByQueue());
    }

    /** @return array<int, QueueTargetData> */
    public function targets(): array
    {
        $targets = [];

        foreach ($this->processesByQueue() as $queue => $connections) {
            foreach (array_keys($connections) as $connection) {
                $targets[] = new QueueTargetData($connection, $queue);
            }
        }

        return $targets;
    }

    /** @return array<string, array<string, int>> */
    private function processesByQueue(): array
    {
        $queues = [];

        foreach ($this->supervisors->all() as $supervisor) {
            $processes = is_array($supervisor->processes ?? null)
                ? $supervisor->processes
                : [];

            foreach ($processes as $descriptor => $processCount) {
                if (! is_string($descriptor) || ! is_numeric($processCount) || ! str_contains($descriptor, ':')) {
                    continue;
                }

                [$connection, $queueNames] = explode(':', $descriptor, 2);
                $connection = trim($connection);

                if ($connection === '') {
                    continue;
                }

                foreach (array_unique(explode(',', $queueNames)) as $queueName) {
                    $queueName = trim($queueName);

                    if ($queueName === '') {
                        continue;
                    }

                    $queues[$queueName][$connection] = ($queues[$queueName][$connection] ?? 0) + (int) $processCount;
                }
            }
        }

        return $queues;
    }

    private function oldestPendingAt(Queue $queue, string $queueName): ?int
    {
        try {
            $createdAt = $queue->creationTimeOfOldestPendingJob($queueName);

            return is_numeric($createdAt) ? (int) $createdAt : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function canCalculateWait(string $queue, int $readyJobs): bool
    {
        if ($readyJobs === 0) {
            return true;
        }

        try {
            return $this->metrics->runtimeForQueue($queue) > 0;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
