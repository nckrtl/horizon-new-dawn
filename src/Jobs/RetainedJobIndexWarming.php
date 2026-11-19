<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class RetainedJobIndexWarming extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The retained job index is warming.');
    }
}
