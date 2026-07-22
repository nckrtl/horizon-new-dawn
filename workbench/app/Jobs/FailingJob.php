<?php

declare(strict_types=1);

namespace Workbench\App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class FailingJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 1;

    public function handle(): never
    {
        throw new RuntimeException('Intentional Workbench failure.');
    }
}
