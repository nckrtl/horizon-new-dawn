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
        $ids = [];
        $sourceTotal = max(0, (int) $this->jobs->countFailed());

        for ($inspected = 0; $inspected < $sourceTotal; $inspected += self::PAGE_SIZE) {
            $rawPageSize = min(self::PAGE_SIZE, $sourceTotal - $inspected);
            $chunk = $this->jobs->getFailed((string) ($inspected - 1));

            foreach ($chunk->take($rawPageSize) as $job) {
                if (! is_object($job) || ! is_string($job->id ?? null) || $job->id === '') {
                    continue;
                }
                $ids[$job->id] = $job->id;
            }
        }

        $removed = 0;

        foreach ($ids as $id) {
            $this->jobs->deleteFailed($id);
            $this->failedJobs->forget($id);
            $removed++;
        }

        return $removed;
    }
}
