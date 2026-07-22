<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Jobs\FailingJob;
use Workbench\App\Jobs\SucceedingJob;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SucceedingJob::dispatch()->onQueue('default');
        FailingJob::dispatch()->onQueue('default');
    }
}
