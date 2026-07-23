<?php

declare(strict_types=1);

use NckRtl\HorizonNewDawn\Jobs\JobListType;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\bindPendingJobOrdering;
use function NckRtl\HorizonNewDawn\Tests\Support\delayedOrderingPayload;
use function NckRtl\HorizonNewDawn\Tests\Support\pendingOrderingJob;

it('pages a large mixed pending index while hydrating only the requested rows', function (): void {
    $base = 1_784_281_000.0;
    $pendingScores = [];
    $delayedScores = [];
    $readyPayloads = [];
    $reservedPayloads = [];
    $readyIds = [];
    $delayedIds = [];

    foreach (range(0, 24_999) as $index) {
        $id = 'job-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);
        $pushedAt = $base + $index;
        $pendingScores[$id] = -$pushedAt;

        if ($index % 5 !== 0) {
            $readyIds[] = $id;

            continue;
        }

        $releaseAt = $base + 100_000 + $index;
        $delayedIds[] = $id;
        $payload = delayedOrderingPayload($id, $pushedAt, 100_000);

        match ($index % 20) {
            0, 5 => $delayedScores[$payload] = $releaseAt,
            10 => $readyPayloads[] = $payload,
            15 => $reservedPayloads[$payload] = $releaseAt + 60,
            default => null,
        };
    }

    $orderedIds = [...$readyIds, ...$delayedIds];
    $startingAt = 19_975;
    $expected = array_slice($orderedIds, $startingAt, 50);
    $jobs = [];

    foreach ($expected as $id) {
        $index = (int) mb_substr($id, 4);
        $pushedAt = $base + $index;
        $releaseAt = $index % 5 === 0 ? $base + 100_000 + $index : null;
        $jobs[$id] = pendingOrderingJob($index, $id, $pushedAt, $releaseAt);
    }

    $hydration = bindPendingJobOrdering(
        $jobs,
        $pendingScores,
        ['queues:default:delayed' => $delayedScores],
        readyPayloads: ['queues:default' => $readyPayloads],
        reservedPayloads: ['queues:default:reserved' => $reservedPayloads],
    );

    memory_reset_peak_usage();
    $memoryBefore = memory_get_usage(true);
    $startedAt = hrtime(true);
    $page = app(JobsData::class)->page(JobListType::Pending, $startingAt - 1);
    $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
    $peakMemoryMegabytes = (memory_get_peak_usage(true) - $memoryBefore) / 1_048_576;

    expect(array_column($page->items, 'id'))->toBe($expected)
        ->and($hydration->ids)->toBe($expected)
        ->and($hydration->ids)->toHaveCount(50)
        ->and($elapsedMilliseconds)->toBeLessThan(5_000)
        ->and($peakMemoryMegabytes)->toBeLessThan(64);
})->group('stress');
