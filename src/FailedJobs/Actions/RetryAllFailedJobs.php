<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Actions;

use Laravel\Horizon\Contracts\JobRepository;

final readonly class RetryAllFailedJobs
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private JobRepository $jobs,
        private RetryFailedJob $retry,
    ) {}

    public function handle(?string $connection = null, ?string $queue = null): int
    {
        $scheduled = 0;
        $seen = [];
        $sourceTotal = max(0, (int) $this->jobs->countFailed());

        for ($inspected = 0; $inspected < $sourceTotal; $inspected += self::PAGE_SIZE) {
            $rawPageSize = min(self::PAGE_SIZE, $sourceTotal - $inspected);
            $chunk = $this->jobs->getFailed((string) ($inspected - 1));

            foreach ($chunk->take($rawPageSize) as $job) {
                if (! is_object($job) || ! is_string($job->id ?? null) || $job->id === '') {
                    continue;
                }

                if ($connection !== null && ($job->connection ?? null) !== $connection) {
                    continue;
                }

                if ($queue !== null && ($job->queue ?? null) !== $queue) {
                    continue;
                }

                if (isset($seen[$job->id])) {
                    continue;
                }

                $seen[$job->id] = true;

                if ($this->retry->handleBulk($job->id, $job)) {
                    $scheduled++;
                }
            }
        }

        return $scheduled;
    }
}
