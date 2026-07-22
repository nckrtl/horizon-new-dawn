<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\Jobs\Data\JobPageData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Monitoring\Data\MonitoredTagData;
use NckRtl\HorizonNewDawn\Monitoring\Data\MonitoringPageData;
use NckRtl\HorizonNewDawn\Monitoring\Data\MonitoringTagSummaryData;
use Throwable;

final readonly class MonitoringData
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private TagRepository $tags,
        private JobRepository $jobs,
        private JobsData $jobData,
    ) {}

    public function index(): MonitoringPageData
    {
        try {
            $items = [];
            $silencedTags = $this->silencedTags();

            foreach (array_unique($this->tags->monitoring()) as $tag) {
                if (! is_string($tag) || $tag === '') {
                    continue;
                }

                $trackedCount = $this->tags->count($tag);
                $failedCount = $this->tags->count("failed:{$tag}");

                $items[] = new MonitoredTagData(
                    tag: $tag,
                    trackedCount: $trackedCount,
                    failedCount: $failedCount,
                    lastActivityAt: $this->lastActivityAt($tag),
                    silenced: in_array($tag, $silencedTags, true),
                );
            }

            usort(
                $items,
                static fn (MonitoredTagData $left, MonitoredTagData $right): int => strnatcasecmp($left->tag, $right->tag),
            );

            return new MonitoringPageData(
                available: true,
                tags: $items,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new MonitoringPageData(
                available: false,
                tags: [],
                message: 'Monitored tags are currently unavailable.',
            );
        }
    }

    public function summary(string $tag): MonitoringTagSummaryData
    {
        try {
            return new MonitoringTagSummaryData(
                tag: $tag,
                trackedCount: $this->tags->count($tag),
                failedCount: $this->tags->count("failed:{$tag}"),
                silenced: in_array($tag, $this->silencedTags(), true),
                monitoredRetentionMinutes: max(0, (int) config('horizon.trim.monitored', 10080)),
                failedRetentionMinutes: max(0, (int) config('horizon.trim.failed', 10080)),
            );
        } catch (Throwable $exception) {
            report($exception);

            return new MonitoringTagSummaryData(
                tag: $tag,
                trackedCount: 0,
                failedCount: 0,
                silenced: in_array($tag, $this->silencedTags(), true),
                monitoredRetentionMinutes: max(0, (int) config('horizon.trim.monitored', 10080)),
                failedRetentionMinutes: max(0, (int) config('horizon.trim.failed', 10080)),
            );
        }
    }

    /** @return list<string> */
    public function monitoredTags(): array
    {
        try {
            $tags = [];

            foreach ($this->tags->monitoring() as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $tags[] = $tag;
                }
            }

            $tags = array_values(array_unique($tags));
            sort($tags, SORT_NATURAL | SORT_FLAG_CASE);

            return $tags;
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    public function page(string $tag, MonitoringStatus $status, int $startingAt): JobPageData
    {
        try {
            $repositoryTag = $status->repositoryTag($tag);
            $ids = $this->tags->paginate($repositoryTag, $startingAt, self::PAGE_SIZE);
            $jobIds = array_values(array_filter($ids, is_string(...)));
            $jobs = $this->jobs->getJobs($jobIds, $startingAt);
            $items = [];

            foreach ($jobs as $job) {
                if (! is_object($job)) {
                    continue;
                }

                $row = $this->jobData->row($job);

                if ($row !== null) {
                    $items[] = $row;
                }
            }

            return new JobPageData(
                available: true,
                items: $items,
                total: $this->tags->count($repositoryTag),
                current: $startingAt,
                next: count($ids) === self::PAGE_SIZE ? $startingAt + self::PAGE_SIZE : null,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new JobPageData(
                available: false,
                items: [],
                total: 0,
                current: $startingAt,
                next: null,
                message: 'Jobs for this tag are currently unavailable.',
            );
        }
    }

    private function lastActivityAt(string $tag): ?float
    {
        $recentIds = array_values(array_filter(
            $this->tags->paginate($tag, 0, 1),
            is_string(...),
        ));
        $failedIds = array_values(array_filter(
            $this->tags->paginate("failed:{$tag}", 0, 1),
            is_string(...),
        ));
        $ids = array_values(array_unique([...$recentIds, ...$failedIds]));

        if ($ids === []) {
            return null;
        }

        $latest = null;

        foreach ($this->jobs->getJobs($ids) as $job) {
            if (! is_object($job)) {
                continue;
            }

            $row = $this->jobData->row($job);
            $occurredAt = $row?->occurredAt;

            if ($occurredAt === null) {
                continue;
            }

            $latest = $latest === null ? $occurredAt : max($latest, $occurredAt);
        }

        return $latest;
    }

    /** @return list<string> */
    private function silencedTags(): array
    {
        $tags = config('horizon.silenced_tags', []);

        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_filter(
            $tags,
            static fn (mixed $tag): bool => is_string($tag) && $tag !== '',
        ));
    }
}
