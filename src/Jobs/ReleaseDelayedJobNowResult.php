<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

enum ReleaseDelayedJobNowResult: string
{
    case Released = 'released';
    case NotDelayed = 'not_delayed';
}
