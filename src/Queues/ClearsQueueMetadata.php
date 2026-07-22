<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

interface ClearsQueueMetadata
{
    public function purgePending(string $connection, string $queue): int;
}
