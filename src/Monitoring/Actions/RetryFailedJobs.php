<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Actions;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;

final readonly class RetryFailedJobs
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private TagRepository $tags,
        private JobRepository $jobs,
        private RetryFailedJob $retry,
    ) {}

    public function handle(string $tag): int
    {
        $repositoryTag = "failed:{$tag}";
        $startingAt = 0;
        $scheduled = 0;
        $seen = [];

        while (true) {
            $ids = array_values($this->tags->paginate($repositoryTag, $startingAt, self::PAGE_SIZE));

            if ($ids === []) {
                break;
            }

            foreach ($this->jobs->getJobs($ids) as $job) {
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

            if (count($ids) < self::PAGE_SIZE) {
                break;
            }

            $startingAt += self::PAGE_SIZE;
        }

        return $scheduled;
    }
}
