<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

enum QueueActivityTab: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Silenced = 'silenced';
    case Batches = 'batches';
}
