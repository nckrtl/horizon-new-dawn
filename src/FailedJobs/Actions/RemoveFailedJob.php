<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Actions;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Laravel\Horizon\Contracts\JobRepository;

final readonly class RemoveFailedJob
{
    public function __construct(
        private JobRepository $jobs,
        private FailedJobProviderInterface $failedJobs,
    ) {}

    public function handle(string $id): void
    {
        $this->jobs->deleteFailed($id);
        $this->failedJobs->forget($id);
    }
}
