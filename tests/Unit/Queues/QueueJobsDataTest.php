<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRetainedJobsData;
use NckRtl\HorizonNewDawn\Queues\QueueActivityTab;
use NckRtl\HorizonNewDawn\Queues\QueueJobsData;
use NckRtl\HorizonNewDawn\Tests\Support\HorizonJob;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

function retainedQueueJobsData(JobRepository $repository): QueueJobsData
{
    $jobs = new JobsData($repository);

    return new QueueJobsData(
        $repository,
        $jobs,
        new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            $jobs,
            new FailedJobRetryEligibility,
        ),
        app(CacheFactory::class),
    );
}

function retainedQueueJob(int $index, string $queue, string $status = 'pending'): HorizonJob
{
    $job = horizonJob($index, "{$status}-{$index}");
    $job->queue = $queue;
    $job->status = $status;

    return $job;
}

beforeEach(function (): void {
    app(CacheFactory::class)->store()->clear();
    config()->set('horizon-new-dawn.poll_interval', 0);
    config()->set('horizon.prefix', 'queue-tests:');
    config()->set('horizon.trim.completed', 60);
    config()->set('horizon.trim.failed', 10080);
    CarbonImmutable::setTestNow('2026-07-20 12:00:00 UTC');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('filters pending completed failed and silenced retained pages by exact queue name', function (): void {
    $repository = mockDashboardContract(JobRepository::class);

    $pending = retainedQueueJob(0, 'reports');
    $otherPending = retainedQueueJob(1, 'reports-low');
    dashboardReturnsFor($repository, 'countPending', [], 2);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect([$pending, $otherPending]));

    $completed = retainedQueueJob(0, 'reports', 'completed');
    dashboardReturnsFor($repository, 'countCompleted', [], 1);
    dashboardReturnsFor($repository, 'getCompleted', ['-1'], collect([$completed]));

    $failed = retainedQueueJob(0, 'reports', 'failed');
    $failed->retried_by = json_encode([
        ['id' => 'retry-1', 'status' => 'completed', 'retried_at' => 100],
    ], JSON_THROW_ON_ERROR);
    dashboardReturnsFor($repository, 'countFailed', [], 1);
    dashboardReturnsFor($repository, 'getFailed', ['-1'], collect([$failed]));

    $silenced = retainedQueueJob(0, 'reports', 'completed');
    $otherSilenced = retainedQueueJob(1, 'reports-low', 'completed');
    dashboardReturnsFor($repository, 'countSilenced', [], 2);
    dashboardReturnsFor($repository, 'getSilenced', ['-1'], collect([$silenced, $otherSilenced]));

    $data = retainedQueueJobsData($repository);
    $pendingPage = $data->page('reports', QueueActivityTab::Pending, -1);
    $completedPage = $data->page('reports', QueueActivityTab::Completed, -1);
    $failedPage = $data->page('reports', QueueActivityTab::Failed, -1);
    $silencedPage = $data->page('reports', QueueActivityTab::Silenced, -1);
    $failedRow = $failedPage->toArray()['rows'][0];

    expect(array_column($pendingPage->toArray()['rows'], 'id'))->toBe(['pending-0'])
        ->and(array_column($completedPage->toArray()['rows'], 'id'))->toBe(['completed-0'])
        ->and(array_column($failedPage->toArray()['rows'], 'id'))->toBe(['failed-0'])
        ->and(array_column($silencedPage->toArray()['rows'], 'id'))->toBe(['completed-0'])
        ->and($failedRow['retried'])->toBeTrue()
        ->and($pendingPage->pageName)->toBe('starting_at')
        ->and($pendingPage->complete)->toBeTrue()
        ->and($pendingPage->next)->toBeNull();
});

it('continues from the final inspected Horizon index after filling a result page', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 51);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect(array_map(
        static fn (int $index): object => retainedQueueJob($index, 'reports'),
        range(0, 49),
    )));

    $page = retainedQueueJobsData($repository)->page('reports', QueueActivityTab::Pending, -1);

    expect($page->total)->toBe(50)
        ->and($page->complete)->toBeFalse()
        ->and($page->current)->toBe(-1)
        ->and($page->next)->toBe(49)
        ->and($page->message)->toBeNull();
});

it('continues from the raw Horizon page boundary when missing hashes leave an empty page', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 51);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect());
    dashboardReturnsFor($repository, 'getPending', ['49'], collect([
        retainedQueueJob(50, 'reports'),
    ]));

    $page = retainedQueueJobsData($repository)->page('reports', QueueActivityTab::Pending, -1);

    expect(array_column($page->toArray()['rows'], 'id'))->toBe(['pending-50'])
        ->and($page->total)->toBe(1)
        ->and($page->complete)->toBeFalse()
        ->and($page->next)->toBeNull()
        ->and($page->message)->toBe('More retained entries may exist for this queue.');
});

it('does not process hydrated jobs beyond the final raw page allowance', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 51);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect(array_map(
        static fn (int $index): object => retainedQueueJob($index, 'other'),
        range(0, 49),
    )));
    dashboardReturnsFor($repository, 'getPending', ['49'], collect([
        retainedQueueJob(50, 'reports'),
        retainedQueueJob(51, 'reports'),
    ]));

    $page = retainedQueueJobsData($repository)->page('reports', QueueActivityTab::Pending, -1);

    expect(array_column($page->toArray()['rows'], 'id'))->toBe(['pending-50'])
        ->and($page->total)->toBe(1)
        ->and($page->complete)->toBeFalse()
        ->and($page->next)->toBeNull();
});

it('keeps a clean final continuation incomplete when earlier hydration provenance is unavailable', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 101);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect(array_map(
        static fn (int $index): object => retainedQueueJob($index, 'reports'),
        range(0, 48),
    )));
    dashboardReturnsFor($repository, 'getPending', ['49'], collect([
        retainedQueueJob(50, 'reports'),
        ...array_map(
            static fn (int $index): object => retainedQueueJob($index, 'other'),
            range(51, 99),
        ),
    ]));
    dashboardReturnsFor($repository, 'countPending', [], 101);
    dashboardReturnsFor($repository, 'getPending', ['99'], collect([
        retainedQueueJob(100, 'reports'),
    ]));

    $data = retainedQueueJobsData($repository);
    $firstPage = $data->page('reports', QueueActivityTab::Pending, -1);
    $finalPage = $data->page('reports', QueueActivityTab::Pending, 99);

    expect($firstPage->complete)->toBeFalse()
        ->and($firstPage->next)->toBe(99)
        ->and(array_column($finalPage->toArray()['rows'], 'id'))->toBe(['pending-100'])
        ->and($finalPage->complete)->toBeFalse()
        ->and($finalPage->next)->toBeNull()
        ->and($finalPage->message)->toBe('More retained entries may exist for this queue.');
});

it('returns a continuation cursor after inspecting 250 nonmatching jobs', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 251);

    foreach (range(0, 4) as $page) {
        $first = $page * 50;
        $cursor = $page === 0 ? '-1' : (string) ($first - 1);
        dashboardReturnsFor($repository, 'getPending', [$cursor], collect(array_map(
            static fn (int $index): object => retainedQueueJob($index, 'other'),
            range($first, $first + 49),
        )));
    }

    $page = retainedQueueJobsData($repository)->page('reports', QueueActivityTab::Pending, -1);

    expect($page->rows)->toBe([])
        ->and($page->total)->toBe(0)
        ->and($page->complete)->toBeFalse()
        ->and($page->next)->toBe(249)
        ->and($page->message)->toBe('More retained entries may exist for this queue.');
});

it('advances by raw page boundaries when Horizon returns reindexed survivor cursors', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 100);
    $page = collect(array_map(
        static fn (int $index): object => retainedQueueJob($index, 'other'),
        range(0, 49),
    ));
    dashboardReturnsFor($repository, 'getPending', ['-1'], $page);
    dashboardReturnsFor($repository, 'getPending', ['49'], $page);

    $result = retainedQueueJobsData($repository)->page('reports', QueueActivityTab::Pending, -1);

    expect($result->rows)->toBe([])
        ->and($result->complete)->toBeTrue()
        ->and($result->next)->toBeNull()
        ->and($result->message)->toBeNull();
});

it('summarizes retained totals and rolling periods and caches for the polling interval', function (): void {
    config()->set('horizon-new-dawn.poll_interval', 5000);
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 1);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect([
        retainedQueueJob(0, 'reports'),
    ]));

    $recentCompleted = retainedQueueJob(0, 'reports', 'completed');
    $recentCompleted->completed_at = (string) CarbonImmutable::now()->subMinutes(30)->timestamp;
    $olderCompleted = retainedQueueJob(1, 'reports', 'completed');
    $olderCompleted->completed_at = (string) CarbonImmutable::now()->subHours(2)->timestamp;
    $otherCompleted = retainedQueueJob(2, 'other', 'completed');
    $otherCompleted->completed_at = (string) CarbonImmutable::now()->subMinutes(10)->timestamp;
    dashboardReturnsFor($repository, 'countCompleted', [], 3);
    dashboardReturnsFor($repository, 'getCompleted', ['-1'], collect([
        $recentCompleted,
        $olderCompleted,
        $otherCompleted,
    ]));

    $recentFailed = retainedQueueJob(0, 'reports', 'failed');
    $recentFailed->failed_at = (string) CarbonImmutable::now()->subMinutes(10)->timestamp;
    $olderFailed = retainedQueueJob(1, 'reports', 'failed');
    $olderFailed->failed_at = (string) CarbonImmutable::now()->subHours(3)->timestamp;
    dashboardReturnsFor($repository, 'countFailed', [], 2);
    dashboardReturnsFor($repository, 'getFailed', ['-1'], collect([
        $recentFailed,
        $olderFailed,
    ]));

    $silenced = retainedQueueJob(0, 'reports', 'completed');
    dashboardReturnsFor($repository, 'countSilenced', [], 1);
    dashboardReturnsFor($repository, 'getSilenced', ['-1'], collect([$silenced]));

    $data = retainedQueueJobsData($repository);
    $summary = $data->summary('reports');
    $cached = $data->summary('reports');

    expect($summary->toArray())->toBe([
        'pending' => 1,
        'pendingComplete' => true,
        'completed' => 2,
        'completedComplete' => true,
        'completedPerMinute' => 0.02,
        'completedPerMinuteComplete' => true,
        'completedPastHour' => 1,
        'completedPastHourComplete' => true,
        'completedPastDay' => 1,
        'completedPastDayComplete' => true,
        'completedRetentionMinutes' => 60,
        'failed' => 2,
        'failedComplete' => true,
        'failedPerMinute' => 0.02,
        'failedPerMinuteComplete' => true,
        'failedPastHour' => 1,
        'failedPastHourComplete' => true,
        'failedPastDay' => 2,
        'failedPastDayComplete' => true,
        'failedRetentionMinutes' => 10080,
        'silenced' => 1,
        'silencedComplete' => true,
        'message' => null,
    ])->and($cached->toArray())->toBe($summary->toArray());
});

it('replaces unserializable legacy job summary objects with scalar cache payloads', function (): void {
    requireConfigurableCacheUnserialization();

    config()->set('cache.stores.array.serialize', true);
    config()->set('cache.serializable_classes', false);
    config()->set('horizon-new-dawn.poll_interval', 5_000);
    app(CacheManager::class)->purge();

    $cache = app(CacheFactory::class)->store();
    $cacheKey = 'horizon-new-dawn:queue-jobs:'.hash(
        'sha256',
        "queue-tests:\0reports",
    );
    $cache->put($cacheKey, new QueueRetainedJobsData(
        pending: 99,
        pendingComplete: true,
        completed: 99,
        completedComplete: true,
        completedPerMinute: 99.0,
        completedPerMinuteComplete: true,
        completedPastHour: 99,
        completedPastHourComplete: true,
        completedPastDay: 99,
        completedPastDayComplete: true,
        completedRetentionMinutes: 60,
        failed: 99,
        failedComplete: true,
        failedPerMinute: 99.0,
        failedPerMinuteComplete: true,
        failedPastHour: 99,
        failedPastHourComplete: true,
        failedPastDay: 99,
        failedPastDayComplete: true,
        failedRetentionMinutes: 10_080,
        silenced: 99,
        silencedComplete: true,
        message: null,
    ), 60);

    expect($cache->get($cacheKey))->toBeInstanceOf(__PHP_Incomplete_Class::class);

    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 0);
    dashboardReturnsFor($repository, 'countCompleted', [], 0);
    dashboardReturnsFor($repository, 'countFailed', [], 0);
    dashboardReturnsFor($repository, 'countSilenced', [], 0);

    $data = retainedQueueJobsData($repository);
    $first = $data->summary('reports');
    $cached = $data->summary('reports');

    expect($first->pending)->toBe(0)
        ->and($cached->pending)->toBe(0)
        ->and($cache->get($cacheKey))->toBeArray();
});

it('scans later raw Horizon pages when missing hashes leave a short hydrated page', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 51);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect([
        retainedQueueJob(0, 'reports'),
    ]));
    dashboardReturnsFor($repository, 'getPending', ['49'], collect([
        retainedQueueJob(50, 'reports'),
    ]));
    dashboardReturnsFor($repository, 'countCompleted', [], 0);
    dashboardReturnsFor($repository, 'countFailed', [], 0);
    dashboardReturnsFor($repository, 'countSilenced', [], 0);

    $summary = retainedQueueJobsData($repository)->summary('reports');

    expect($summary->pending)->toBe(2)
        ->and($summary->pendingComplete)->toBeFalse();
});

it('does not summarize hydrated jobs beyond the final raw page allowance', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 51);
    dashboardReturnsFor($repository, 'getPending', ['-1'], collect(array_map(
        static fn (int $index): object => retainedQueueJob($index, 'other'),
        range(0, 49),
    )));
    dashboardReturnsFor($repository, 'getPending', ['49'], collect([
        retainedQueueJob(50, 'reports'),
        retainedQueueJob(51, 'reports'),
    ]));
    dashboardReturnsFor($repository, 'countCompleted', [], 0);
    dashboardReturnsFor($repository, 'countFailed', [], 0);
    dashboardReturnsFor($repository, 'countSilenced', [], 0);

    $summary = retainedQueueJobsData($repository)->summary('reports');

    expect($summary->pending)->toBe(1)
        ->and($summary->pendingComplete)->toBeFalse();
});

it('bypasses summary caching when automatic polling is disabled', function (): void {
    $repository = mockDashboardContract(JobRepository::class);

    foreach (range(1, 2) as $_) {
        dashboardReturnsFor($repository, 'countPending', [], 1);
        dashboardReturnsFor($repository, 'getPending', ['-1'], collect([
            retainedQueueJob(0, 'reports'),
        ]));
        dashboardReturnsFor($repository, 'countCompleted', [], 0);
        dashboardReturnsFor($repository, 'countFailed', [], 0);
        dashboardReturnsFor($repository, 'countSilenced', [], 0);
    }

    $data = retainedQueueJobsData($repository);

    expect($data->summary('reports')->pending)->toBe(1)
        ->and($data->summary('reports')->pending)->toBe(1);
});

it('marks a capped retained summary as incomplete', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 251);

    foreach (range(0, 4) as $page) {
        $first = $page * 50;
        $cursor = $page === 0 ? '-1' : (string) ($first - 1);
        dashboardReturnsFor($repository, 'getPending', [$cursor], collect(array_map(
            static fn (int $index): object => retainedQueueJob($index, 'reports'),
            range($first, $first + 49),
        )));
    }

    dashboardReturnsFor($repository, 'countCompleted', [], 0);
    dashboardReturnsFor($repository, 'countFailed', [], 0);
    dashboardReturnsFor($repository, 'countSilenced', [], 0);

    $summary = retainedQueueJobsData($repository)->summary('reports');

    expect($summary->pending)->toBe(250)
        ->and($summary->pendingComplete)->toBeFalse();
});

it('marks every bounded rolling statistic as a lower bound', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countPending', [], 0);
    dashboardReturnsFor($repository, 'countCompleted', [], 251);

    foreach (range(0, 4) as $page) {
        $first = $page * 50;
        $cursor = $page === 0 ? '-1' : (string) ($first - 1);
        $jobs = array_map(function (int $index): object {
            $job = retainedQueueJob($index, 'reports', 'completed');
            $job->completed_at = (string) CarbonImmutable::now()->subMinutes(5)->timestamp;

            return $job;
        }, range($first, $first + 49));
        dashboardReturnsFor($repository, 'getCompleted', [$cursor], collect($jobs));
    }

    dashboardReturnsFor($repository, 'countFailed', [], 0);
    dashboardReturnsFor($repository, 'countSilenced', [], 0);

    $summary = retainedQueueJobsData($repository)->summary('reports');

    expect($summary->completed)->toBe(250)
        ->and($summary->completedComplete)->toBeFalse()
        ->and($summary->completedPerMinuteComplete)->toBeFalse()
        ->and($summary->completedPastHourComplete)->toBeFalse()
        ->and($summary->completedPastDayComplete)->toBeFalse();
});

it('isolates an exception to the requested retained collection', function (): void {
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($repository, 'countFailed', [], 1);
    dashboardThrowsFor($repository, 'getFailed', ['-1'], new RuntimeException('redis secret'));

    $page = retainedQueueJobsData($repository)->page('reports', QueueActivityTab::Failed, -1);

    expect($page->available)->toBeFalse()
        ->and($page->rows)->toBe([])
        ->and($page->message)->toBe('Retained failed jobs are currently unavailable.')
        ->and($page->message)->not->toContain('secret');
});
