<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use NckRtl\HorizonNewDawn\Queues\Data\QueueWaitThresholdData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueWaitThresholdTargetData;

final readonly class QueueWaitThreshold
{
    private const int DEFAULT_THRESHOLD_SECONDS = 60;

    public function __construct(private Repository $config) {}

    public function forTarget(
        string $connection,
        string $queue,
        int|float|null $waitSeconds,
        ?int $oldestPendingAt,
    ): QueueWaitThresholdTargetData {
        $configuredThreshold = $this->config->get("horizon.waits.{$connection}:{$queue}");
        $thresholdSeconds = $configuredThreshold === null
            ? self::DEFAULT_THRESHOLD_SECONDS
            : (int) $configuredThreshold;
        $monitored = $configuredThreshold !== 0;

        $status = match (true) {
            ! $monitored => QueueWaitThresholdStatus::Disabled,
            $waitSeconds === null => QueueWaitThresholdStatus::Calculating,
            $waitSeconds > $thresholdSeconds => QueueWaitThresholdStatus::Exceeded,
            default => QueueWaitThresholdStatus::WithinBounds,
        };

        return new QueueWaitThresholdTargetData(
            connection: $connection,
            status: $status,
            monitored: $monitored,
            waitSeconds: $waitSeconds,
            thresholdSeconds: $thresholdSeconds,
            oldestReadyAgeSeconds: $oldestPendingAt === null
                ? null
                : max(0, CarbonImmutable::now()->getTimestamp() - $oldestPendingAt),
        );
    }

    /** @param array<int, QueueWaitThresholdTargetData> $targets */
    public function summarize(array $targets): QueueWaitThresholdData
    {
        if ($targets === []) {
            throw new InvalidArgumentException('Queue wait threshold requires at least one target.');
        }

        usort(
            $targets,
            fn (QueueWaitThresholdTargetData $left, QueueWaitThresholdTargetData $right): int => strnatcasecmp(
                $left->connection,
                $right->connection,
            ),
        );

        $decisive = $this->decisiveTarget($targets);
        $oldest = $this->oldestTarget($targets);

        return new QueueWaitThresholdData(
            status: $decisive->status,
            decisiveConnection: $decisive->connection,
            waitSeconds: $decisive->waitSeconds,
            thresholdSeconds: $decisive->thresholdSeconds,
            oldestReadyAgeSeconds: $oldest?->oldestReadyAgeSeconds,
            oldestReadyConnection: $oldest?->connection,
            targets: $targets,
        );
    }

    /**
     * @param  array<int, QueueWaitThresholdTargetData>  $targets
     */
    private function decisiveTarget(array $targets): QueueWaitThresholdTargetData
    {
        $exceeded = array_values(array_filter(
            $targets,
            fn (QueueWaitThresholdTargetData $target): bool => $target->status === QueueWaitThresholdStatus::Exceeded,
        ));

        if ($exceeded !== []) {
            usort($exceeded, function (QueueWaitThresholdTargetData $left, QueueWaitThresholdTargetData $right): int {
                $rightOverage = ($right->waitSeconds ?? 0) - $right->thresholdSeconds;
                $leftOverage = ($left->waitSeconds ?? 0) - $left->thresholdSeconds;
                $overage = $rightOverage <=> $leftOverage;

                return $overage !== 0 ? $overage : strnatcasecmp($left->connection, $right->connection);
            });

            return $exceeded[0];
        }

        $calculating = array_values(array_filter(
            $targets,
            fn (QueueWaitThresholdTargetData $target): bool => $target->status === QueueWaitThresholdStatus::Calculating,
        ));

        if ($calculating !== []) {
            usort(
                $calculating,
                fn (QueueWaitThresholdTargetData $left, QueueWaitThresholdTargetData $right): int => strnatcasecmp(
                    $left->connection,
                    $right->connection,
                ),
            );

            return $calculating[0];
        }

        $monitored = array_values(array_filter(
            $targets,
            fn (QueueWaitThresholdTargetData $target): bool => $target->monitored,
        ));

        if ($monitored !== []) {
            usort($monitored, function (QueueWaitThresholdTargetData $left, QueueWaitThresholdTargetData $right): int {
                $headroom = ($left->thresholdSeconds - $left->waitSeconds)
                    <=> ($right->thresholdSeconds - $right->waitSeconds);

                return $headroom !== 0 ? $headroom : strnatcasecmp($left->connection, $right->connection);
            });

            return $monitored[0];
        }

        usort($targets, function (QueueWaitThresholdTargetData $left, QueueWaitThresholdTargetData $right): int {
            $wait = $right->waitSeconds <=> $left->waitSeconds;

            return $wait !== 0 ? $wait : strnatcasecmp($left->connection, $right->connection);
        });

        return $targets[0];
    }

    /**
     * @param  array<int, QueueWaitThresholdTargetData>  $targets
     */
    private function oldestTarget(array $targets): ?QueueWaitThresholdTargetData
    {
        $withOldestReadyJob = array_values(array_filter(
            $targets,
            fn (QueueWaitThresholdTargetData $target): bool => $target->oldestReadyAgeSeconds !== null,
        ));

        if ($withOldestReadyJob === []) {
            return null;
        }

        usort($withOldestReadyJob, function (QueueWaitThresholdTargetData $left, QueueWaitThresholdTargetData $right): int {
            $age = $right->oldestReadyAgeSeconds <=> $left->oldestReadyAgeSeconds;

            return $age !== 0 ? $age : strnatcasecmp($left->connection, $right->connection);
        });

        return $withOldestReadyJob[0];
    }
}
