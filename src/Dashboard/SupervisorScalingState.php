<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard;

enum SupervisorScalingState: string
{
    case Up = 'up';
    case Down = 'down';
    case Steady = 'steady';
}
