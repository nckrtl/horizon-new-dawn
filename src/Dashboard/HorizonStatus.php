<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard;

enum HorizonStatus: string
{
    case Running = 'running';
    case Paused = 'paused';
    case Inactive = 'inactive';
    case Unavailable = 'unavailable';
}
