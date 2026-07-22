<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support;

enum NavigationItem: string
{
    case Dashboard = 'dashboard';
    case Instances = 'instances';
    case Queues = 'queues';
    case Monitoring = 'monitoring';
    case Metrics = 'metrics';
    case Batches = 'batches';
    case Pending = 'pending';
    case Completed = 'completed';
    case Silenced = 'silenced';
    case Failed = 'failed';
}
