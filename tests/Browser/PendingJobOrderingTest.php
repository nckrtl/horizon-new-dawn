<?php

declare(strict_types=1);

use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserPageFixtures;
use function NckRtl\HorizonNewDawn\Tests\Support\bindPendingJobOrdering;
use function NckRtl\HorizonNewDawn\Tests\Support\delayedOrderingPayload;
use function NckRtl\HorizonNewDawn\Tests\Support\pendingOrderingJob;

it('renders the globally earliest pending jobs before a later scheduled job', function (): void {
    bindBrowserPageFixtures();

    $base = 1_784_281_000.0;
    $jobs = [];
    $pendingScores = [];

    foreach (range(0, 59) as $index) {
        $id = "job-{$index}";
        $pushedAt = $base + $index;
        $releaseAt = $index === 0 ? $base + 1_000 : null;
        $jobs[$id] = pendingOrderingJob($index, $id, $pushedAt, $releaseAt);
        $pendingScores[$id] = -$pushedAt;
    }

    $delayedPayload = delayedOrderingPayload('job-0', $base, 1_000);
    bindPendingJobOrdering(
        $jobs,
        $pendingScores,
        ['queues:default:delayed' => [$delayedPayload => $base + 1_000]],
    );

    $page = visit('/horizon/jobs/pending')->assertSee('Pending Jobs');
    $renderedJobs = $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('tbody tr')).map(
            (row) => row.querySelector('td a')?.textContent?.trim() ?? '',
        )
    JS);
    $expectedFirstPage = array_map(
        static fn (int $index): string => 'PendingJob'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
        range(1, 50),
    );

    expect(array_slice($renderedJobs, 0, 50))->toBe($expectedFirstPage);

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('renders a manually released job before jobs that are still delayed', function (): void {
    bindBrowserPageFixtures();

    $base = 1_784_281_000.0;
    $released = pendingOrderingJob(0, 'released-job', $base, $base + 7_200);
    $delayed = pendingOrderingJob(1, 'delayed-job', $base + 1, $base + 3_600);
    $releasedPayload = json_decode(
        delayedOrderingPayload('released-job', $base, 7_200),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $releasedPayload['horizonNewDawn'] = ['madeAvailableAt' => $base + 60];
    $releasedPayload = json_encode($releasedPayload, JSON_THROW_ON_ERROR);
    $released->payload = $releasedPayload;

    bindPendingJobOrdering(
        [
            'released-job' => $released,
            'delayed-job' => $delayed,
        ],
        [
            'released-job' => -$base,
            'delayed-job' => -($base + 1),
        ],
        [
            'queues:default:delayed' => [
                delayedOrderingPayload('delayed-job', $base + 1, 3_599) => $base + 3_600,
            ],
        ],
        readyPayloads: ['queues:default' => [$releasedPayload]],
    );

    $page = visit('/horizon/jobs/pending')->assertSee('Pending Jobs');
    $renderedJobs = $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('tbody tr')).map(
            (row) => row.querySelector('td a')?.textContent?.trim() ?? '',
        )
    JS);

    expect(array_slice($renderedJobs, 0, 2))->toBe([
        'PendingJob00000',
        'PendingJob00001',
    ]);

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('renders scheduled jobs as delayed, released, and reserved without changing Horizon state', function (): void {
    bindBrowserPageFixtures();

    $now = (float) time();
    $released = pendingOrderingJob(0, 'released-job', $now - 120, $now - 60);
    $released->delay = 0;
    $reserved = pendingOrderingJob(1, 'reserved-job', $now - 119, $now - 59);
    $reserved->status = 'reserved';
    $reserved->delay = 0;
    $delayed = pendingOrderingJob(2, 'delayed-job', $now, $now + 3_600);

    bindPendingJobOrdering(
        [
            'released-job' => $released,
            'reserved-job' => $reserved,
            'delayed-job' => $delayed,
        ],
        [
            'released-job' => -($now - 120),
            'reserved-job' => -($now - 119),
            'delayed-job' => -$now,
        ],
        [
            'queues:default:delayed' => [
                delayedOrderingPayload('delayed-job', $now, 3_600) => $now + 3_600,
            ],
        ],
        readyPayloads: [
            'queues:default' => [
                delayedOrderingPayload('released-job', $now - 120, 60),
            ],
        ],
        reservedPayloads: [
            'queues:default:reserved' => [
                delayedOrderingPayload('reserved-job', $now - 119, 60) => $now + 60,
            ],
        ],
    );

    $page = visit('/horizon/jobs/pending')
        ->assertSee('Released')
        ->assertSee('Reserved')
        ->assertSee('Delayed');

    $page
        ->click('a[href$="/released-job"]')
        ->assertAriaAttribute(
            '[role="status"][aria-label="Job status: Released"]',
            'label',
            'Job status: Released',
        )
        ->assertSee('Created at')
        ->assertSee('Scheduled at')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('renders a retry as ready instead of released from inherited scheduling metadata', function (): void {
    bindBrowserPageFixtures();

    $now = (float) time();
    $retry = pendingOrderingJob(0, 'retry-job', $now - 120, $now - 60);
    $retry->delay = 0;
    $retryPayload = json_decode($retry->payload, true, flags: JSON_THROW_ON_ERROR);
    $retryPayload['retry_of'] = 'failed-parent';
    $retry->payload = json_encode($retryPayload, JSON_THROW_ON_ERROR);

    bindPendingJobOrdering(
        ['retry-job' => $retry],
        ['retry-job' => -($now - 120)],
        [],
        readyPayloads: ['queues:default' => [$retry->payload]],
    );

    $page = visit('/horizon/jobs/pending')
        ->assertSee('Retry')
        ->assertSee('Ready')
        ->assertDontSee('Released');

    $page
        ->click('a[href$="/retry-job"]')
        ->assertAriaAttribute(
            '[role="status"][aria-label="Job status: Ready"]',
            'label',
            'Job status: Ready',
        )
        ->assertSee('Created at')
        ->assertDontSee('Scheduled at')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
