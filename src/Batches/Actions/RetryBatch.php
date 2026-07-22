<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Actions;

use Illuminate\Bus\BatchRepository;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;

final readonly class RetryBatch
{
    public function __construct(
        private BatchRepository $batches,
        private JobRepository $jobs,
        private RetryFailedJob $retry,
    ) {}

    public function handle(string $id): int
    {
        $batch = $this->batches->find($id);

        if ($batch === null) {
            return 0;
        }

        $scheduled = 0;
        $seen = [];

        foreach ($this->jobs->getJobs($batch->failedJobIds) as $job) {
            if (! is_object($job) || ! is_string($job->id ?? null) || $job->id === '') {
                continue;
            }

            if (isset($seen[$job->id])) {
                continue;
            }

            $seen[$job->id] = true;

            if ($this->retry->handle($job->id, $job)) {
                $scheduled++;
            }
        }

        return $scheduled;
    }
}
