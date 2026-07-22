<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues\Data;

use Spatie\LaravelData\Data;

final class QueueRetainedJobsData extends Data
{
    public function __construct(
        public readonly int $pending,
        public readonly bool $pendingComplete,
        public readonly int $completed,
        public readonly bool $completedComplete,
        public readonly float $completedPerMinute,
        public readonly bool $completedPerMinuteComplete,
        public readonly int $completedPastHour,
        public readonly bool $completedPastHourComplete,
        public readonly int $completedPastDay,
        public readonly bool $completedPastDayComplete,
        public readonly int $completedRetentionMinutes,
        public readonly int $failed,
        public readonly bool $failedComplete,
        public readonly float $failedPerMinute,
        public readonly bool $failedPerMinuteComplete,
        public readonly int $failedPastHour,
        public readonly bool $failedPastHourComplete,
        public readonly int $failedPastDay,
        public readonly bool $failedPastDayComplete,
        public readonly int $failedRetentionMinutes,
        public readonly int $silenced,
        public readonly bool $silencedComplete,
        public readonly ?string $message,
    ) {}
}
