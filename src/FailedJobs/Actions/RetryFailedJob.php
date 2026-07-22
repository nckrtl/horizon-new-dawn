<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;

final readonly class RetryFailedJob
{
    public function __construct(
        private Dispatcher $bus,
        private JobRepository $jobs,
        private FailedJobRetryEligibility $eligibility,
    ) {}

    public function handle(string $id, ?object $job = null): bool
    {
        $job ??= $this->jobs->findFailed($id);

        if (! is_object($job) || ($job->id ?? null) !== $id || ! $this->eligibility->allows($job)) {
            return false;
        }

        $this->bus->dispatch(new HorizonRetryFailedJob($id));

        return true;
    }
}
