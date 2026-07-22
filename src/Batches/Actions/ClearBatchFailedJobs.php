<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Actions;

use Illuminate\Bus\BatchRepository;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RemoveFailedJob;

final readonly class ClearBatchFailedJobs
{
    public function __construct(
        private BatchRepository $batches,
        private RemoveFailedJob $remove,
    ) {}

    public function handle(string $id): int
    {
        $batch = $this->batches->find($id);

        if ($batch === null) {
            return 0;
        }

        $cleared = 0;
        $seen = [];

        foreach ($batch->failedJobIds as $jobId) {
            if (! is_string($jobId) || trim($jobId) === '' || isset($seen[$jobId])) {
                continue;
            }

            $seen[$jobId] = true;
            $this->remove->handle($jobId);
            $cleared++;
        }

        return $cleared;
    }
}
