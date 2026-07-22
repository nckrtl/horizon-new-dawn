<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringData;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringStatus;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('MonitoringData', function (): void {
    it('sorts monitored tags and reports tracked, failed, activity, and silence', function (): void {
        config()->set('horizon.silenced_tags', ['alpha']);
        config()->set('horizon.trim.monitored', 1440);
        config()->set('horizon.trim.failed', 2880);

        $alphaRecent = horizonJob(0, 'alpha-recent');
        $alphaRecent->completed_at = '1784281100.00';
        $alphaRecent->failed_at = null;
        $zetaFailed = horizonJob(1, 'zeta-failed');
        $zetaFailed->completed_at = null;
        $zetaFailed->failed_at = '1784281200.00';

        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['zeta', 'alpha']);
        dashboardReturnsFor($tags, 'count', ['zeta'], 7);
        dashboardReturnsFor($tags, 'count', ['failed:zeta'], 2);
        dashboardReturnsFor($tags, 'count', ['alpha'], 3);
        dashboardReturnsFor($tags, 'count', ['failed:alpha'], 1);
        dashboardReturnsFor($tags, 'paginate', ['zeta', 0, 1], []);
        dashboardReturnsFor($tags, 'paginate', ['failed:zeta', 0, 1], [0 => 'zeta-failed']);
        dashboardReturnsFor($tags, 'paginate', ['alpha', 0, 1], [0 => 'alpha-recent']);
        dashboardReturnsFor($tags, 'paginate', ['failed:alpha', 0, 1], []);

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [['zeta-failed']], new Collection([$zetaFailed]));
        dashboardReturnsFor($jobs, 'getJobs', [['alpha-recent']], new Collection([$alphaRecent]));

        $page = (new MonitoringData($tags, $jobs, new JobsData($jobs)))->index();

        expect($page->available)->toBeTrue()
            ->and(array_map(static fn ($tag): string => $tag->tag, $page->tags))->toBe(['alpha', 'zeta'])
            ->and(array_map(static fn ($tag): int => $tag->trackedCount, $page->tags))->toBe([3, 7])
            ->and(array_map(static fn ($tag): int => $tag->failedCount, $page->tags))->toBe([1, 2])
            ->and($page->tags[0]->silenced)->toBeTrue()
            ->and($page->tags[1]->silenced)->toBeFalse()
            ->and($page->tags[0]->lastActivityAt)->toBe(1_784_281_100.0)
            ->and($page->tags[1]->lastActivityAt)->toBe(1_784_281_200.0);
    });

    it('returns an explicit unavailable monitored-tag state', function (): void {
        $tags = mockDashboardContract(TagRepository::class);
        dashboardThrows($tags, 'monitoring', new RuntimeException('redis unavailable'));
        $jobs = mockDashboardContract(JobRepository::class);

        $page = (new MonitoringData($tags, $jobs, new JobsData($jobs)))->index();

        expect($page->available)->toBeFalse()
            ->and($page->tags)->toBe([])
            ->and($page->message)->toBe('Monitored tags are currently unavailable.');
    });

    it('builds a tag summary with retention configuration', function (): void {
        config()->set('horizon.silenced_tags', ['checkout']);
        config()->set('horizon.trim.monitored', 120);
        config()->set('horizon.trim.failed', 240);

        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($tags, 'count', ['checkout'], 4);
        dashboardReturnsFor($tags, 'count', ['failed:checkout'], 2);
        $jobs = mockDashboardContract(JobRepository::class);

        $summary = (new MonitoringData($tags, $jobs, new JobsData($jobs)))->summary('checkout');

        expect($summary->tag)->toBe('checkout')
            ->and($summary->trackedCount)->toBe(4)
            ->and($summary->failedCount)->toBe(2)
            ->and($summary->silenced)->toBeTrue()
            ->and($summary->monitoredRetentionMinutes)->toBe(120)
            ->and($summary->failedRetentionMinutes)->toBe(240);
    });

    it('lists currently monitored tags', function (): void {
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['zeta', 'alpha', 'alpha']);
        $jobs = mockDashboardContract(JobRepository::class);

        expect((new MonitoringData($tags, $jobs, new JobsData($jobs)))->monitoredTags())
            ->toBe(['alpha', 'zeta']);
    });

    it('normalizes tag jobs and exposes offset scroll metadata', function (): void {
        $tagIds = array_combine(
            range(0, 49),
            array_map(static fn (int $index): string => "job-{$index}", range(0, 49)),
        );
        $delayed = horizonJob(0, 'job-0');
        $delayed->status = 'pending';
        $delayed->delay = 60;
        $jobRows = new Collection([
            $delayed,
            ...array_map(
                static fn (int $index): object => horizonJob($index, "job-{$index}"),
                range(1, 49),
            ),
        ]);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($tags, 'paginate', ['checkout', 0, 50], $tagIds);
        dashboardReturnsFor($tags, 'count', ['checkout'], 80);
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [$tagIds, 0], $jobRows);

        $page = (new MonitoringData($tags, $jobs, new JobsData($jobs)))
            ->page('checkout', MonitoringStatus::Jobs, 0);

        expect($page->total)->toBe(80)
            ->and($page->items)->toHaveCount(50)
            ->and($page->next)->toBe(50)
            ->and($page->items[0]->delay)->toBe(60)
            ->and($page->items[0]->toArray())->not->toHaveKeys(['payload', 'exception', 'context']);
    });

    it('uses the failed tag boundary and stops on a short page', function (): void {
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($tags, 'paginate', ['failed:checkout', 50, 50], [50 => 'failed-1']);
        dashboardReturnsFor($tags, 'count', ['failed:checkout'], 51);
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [['failed-1'], 50], new Collection([
            horizonJob(50, 'failed-1'),
        ]));

        $page = (new MonitoringData($tags, $jobs, new JobsData($jobs)))
            ->page('checkout', MonitoringStatus::Failed, 50);

        expect($page->total)->toBe(51)
            ->and($page->current)->toBe(50)
            ->and($page->next)->toBeNull();
    });
});
