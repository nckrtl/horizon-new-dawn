<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Metrics;

enum MetricType: string
{
    case Jobs = 'jobs';
    case Queues = 'queues';

    public function singular(): string
    {
        return match ($this) {
            self::Jobs => 'Job',
            self::Queues => 'Queue',
        };
    }
}
