<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

enum PendingJobCancellationScope: string
{
    case Ready = 'ready';
    case Delayed = 'delayed';
    case Pending = 'pending';

    public function includesReadyJobs(): bool
    {
        return $this !== self::Delayed;
    }

    public function includesDelayedJobs(): bool
    {
        return $this !== self::Ready;
    }
}
