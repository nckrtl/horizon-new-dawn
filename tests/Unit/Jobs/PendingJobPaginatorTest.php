<?php

declare(strict_types=1);

use NckRtl\HorizonNewDawn\Jobs\JobListType;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\bindPendingJobOrdering;
use function NckRtl\HorizonNewDawn\Tests\Support\delayedOrderingPayload;
use function NckRtl\HorizonNewDawn\Tests\Support\pendingOrderingJob;

it('orders the complete pending set by effective availability before hydrating a page', function (): void {
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
    $hydration = bindPendingJobOrdering(
        $jobs,
        $pendingScores,
        ['queues:default:delayed' => [$delayedPayload => $base + 1_000]],
    );

    $page = app(JobsData::class)->page(JobListType::Pending, -1);
    $expected = array_map(static fn (int $index): string => "job-{$index}", range(1, 50));

    expect(array_column($page->items, 'id'))->toBe($expected)
        ->and($hydration->ids)->toBe($expected)
        ->and($hydration->ids)->toHaveCount(50)
        ->and($page->next)->toBe(49);
});

it('continues the same effective ordering on the next pending page', function (): void {
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
    $hydration = bindPendingJobOrdering(
        $jobs,
        $pendingScores,
        ['queues:default:delayed' => [$delayedPayload => $base + 1_000]],
    );

    $page = app(JobsData::class)->page(JobListType::Pending, 49);
    $expected = [
        ...array_map(static fn (int $index): string => "job-{$index}", range(51, 59)),
        'job-0',
    ];

    expect(array_column($page->items, 'id'))->toBe($expected)
        ->and($hydration->ids)->toBe($expected)
        ->and($page->next)->toBeNull();
});

it('keeps a migrated scheduled job ordered by release time from its ready payload', function (): void {
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

    $migratedPayload = delayedOrderingPayload('job-0', $base, 1_000);
    $hydration = bindPendingJobOrdering(
        $jobs,
        $pendingScores,
        [],
        readyPayloads: ['queues:default' => [$migratedPayload]],
    );

    $page = app(JobsData::class)->page(JobListType::Pending, -1);
    $expected = array_map(static fn (int $index): string => "job-{$index}", range(1, 50));

    expect(array_column($page->items, 'id'))->toBe($expected)
        ->and($hydration->ids)->toBe($expected)
        ->and($hydration->ids)->toHaveCount(50);
});

it('orders a manually released job before jobs that are still delayed', function (): void {
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

    $hydration = bindPendingJobOrdering(
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

    $page = app(JobsData::class)->page(JobListType::Pending, -1);

    expect(array_column($page->items, 'id'))->toBe(['released-job', 'delayed-job'])
        ->and($hydration->ids)->toBe(['released-job', 'delayed-job']);
});

it('keeps a migrated scheduled job ordered by release time from its reserved payload', function (): void {
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

    $migratedPayload = delayedOrderingPayload('job-0', $base, 1_000);
    $hydration = bindPendingJobOrdering(
        $jobs,
        $pendingScores,
        [],
        reservedPayloads: [
            'queues:default:reserved' => [$migratedPayload => $base + 60],
        ],
    );

    $page = app(JobsData::class)->page(JobListType::Pending, 49);
    $expected = [
        ...array_map(static fn (int $index): string => "job-{$index}", range(51, 59)),
        'job-0',
    ];

    expect(array_column($page->items, 'id'))->toBe($expected)
        ->and($hydration->ids)->toBe($expected)
        ->and($page->next)->toBeNull();
});

it('orders a retry by its new pushed time instead of inherited scheduling metadata', function (): void {
    $base = 1_784_281_000.0;
    $retry = pendingOrderingJob(0, 'retry-job', $base);
    $ready = pendingOrderingJob(1, 'ready-job', $base + 1);
    $retryPayload = json_decode(
        delayedOrderingPayload('retry-job', $base - 1_000, 2_000),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $retryPayload['retry_of'] = 'failed-parent';
    $retryPayload = json_encode($retryPayload, JSON_THROW_ON_ERROR);

    $hydration = bindPendingJobOrdering(
        [
            'retry-job' => $retry,
            'ready-job' => $ready,
        ],
        [
            'retry-job' => -$base,
            'ready-job' => -($base + 1),
        ],
        [],
        readyPayloads: ['queues:default' => [$retryPayload]],
    );

    $page = app(JobsData::class)->page(JobListType::Pending, -1);

    expect(array_column($page->items, 'id'))->toBe(['retry-job', 'ready-job'])
        ->and($hydration->ids)->toBe(['retry-job', 'ready-job']);
});
