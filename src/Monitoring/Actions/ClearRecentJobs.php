<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Actions;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringTagGuard;

final readonly class ClearRecentJobs
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private TagRepository $tags,
        private JobRepository $jobs,
        private MonitoringTagGuard $guard,
    ) {}

    public function handle(string $tag): int
    {
        $this->guard->ensureMonitored($this->tags, $tag);

        $startingAt = 0;
        $cleared = 0;

        while (true) {
            $ids = array_values($this->tags->paginate($tag, $startingAt, self::PAGE_SIZE));

            if ($ids === []) {
                break;
            }

            $this->jobs->deleteMonitored($ids);
            $cleared += count($ids);

            if (count($ids) < self::PAGE_SIZE) {
                break;
            }

            $startingAt += self::PAGE_SIZE;
        }

        $this->tags->forget($tag);

        return $cleared;
    }
}
