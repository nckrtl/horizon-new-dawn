<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs\Actions;

use Illuminate\Support\Collection;
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
        $afterIndex = -1;
        $scheduled = 0;
        $seen = [];

        while (true) {
            $chunk = $this->jobs->getFailed((string) $afterIndex);

            if ($chunk->isEmpty()) {
                break;
            }

            foreach ($chunk as $job) {
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

            if ($chunk->count() < self::PAGE_SIZE) {
                break;
            }

            $nextIndex = $this->lastIndex($chunk);

            if ($nextIndex === null || $nextIndex <= $afterIndex) {
                break;
            }

            $afterIndex = $nextIndex;
        }

        return $scheduled;
    }

    /** @param Collection<int, mixed> $jobs */
    private function lastIndex(Collection $jobs): ?int
    {
        $last = $jobs->last();

        return is_object($last) && is_numeric($last->index ?? null) ? (int) $last->index : null;
    }
}
