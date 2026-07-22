<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

enum BatchClearScope: string
{
    case Incomplete = 'incomplete';
    case Complete = 'complete';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function includesCompleteBatches(): bool
    {
        return $this === self::Complete || $this === self::Finished;
    }

    public function includesIncompleteBatches(): bool
    {
        return $this === self::Incomplete || $this === self::Finished;
    }
}
