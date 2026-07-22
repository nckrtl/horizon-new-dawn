<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

enum PendingJobCancellationResult: string
{
    case Cancelled = 'cancelled';
    case NotCancellable = 'not_cancellable';
    case Batched = 'batched';
}
