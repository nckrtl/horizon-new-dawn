<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Actions;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Laravel\Horizon\Contracts\JobRepository;

final readonly class ClearFailedJobs
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private JobRepository $jobs,
        private FailedJobProviderInterface $failedJobs,
    ) {}

    public function handle(): int
    {
        $removed = 0;

        do {
            $chunk = $this->jobs->getFailed();
            $chunkRemoved = 0;

            foreach ($chunk as $job) {
                if (! is_object($job) || ! is_string($job->id ?? null) || $job->id === '') {
                    continue;
                }

                $this->jobs->deleteFailed($job->id);
                $this->failedJobs->forget($job->id);
                $chunkRemoved++;
            }

            $removed += $chunkRemoved;
        } while ($chunk->count() === self::PAGE_SIZE && $chunkRemoved > 0);

        return $removed;
    }
}
