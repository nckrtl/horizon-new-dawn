<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThresholdStatus;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestampUTC(1800));

    config()->set('horizon.waits', [
        'redis:custom' => 30,
        'redis:disabled' => 0,
        'database:disabled' => 0,
        'sqs:reports' => 100,
        'sqs:disabled' => 0,
    ]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('uses configured, default, and disabled Horizon wait thresholds', function (): void {
    $waitThreshold = new QueueWaitThreshold(app(Repository::class));

    $equalToThreshold = $waitThreshold->forTarget('redis', 'custom', 30, 1680);
    $overThreshold = $waitThreshold->forTarget('redis', 'custom', 31, null);
    $defaultThreshold = $waitThreshold->forTarget('redis', 'unconfigured', 60, null);
    $disabled = $waitThreshold->forTarget('redis', 'disabled', 999, null);

    expect($equalToThreshold->toArray())->toBe([
        'connection' => 'redis',
        'status' => QueueWaitThresholdStatus::WithinBounds->value,
        'monitored' => true,
        'waitSeconds' => 30,
        'thresholdSeconds' => 30,
        'oldestReadyAgeSeconds' => 120,
    ])->and($overThreshold->status)->toBe(QueueWaitThresholdStatus::Exceeded)
        ->and($defaultThreshold->status)->toBe(QueueWaitThresholdStatus::WithinBounds)
        ->and($defaultThreshold->thresholdSeconds)->toBe(60)
        ->and($disabled->status)->toBe(QueueWaitThresholdStatus::Disabled)
        ->and($disabled->monitored)->toBeFalse()
        ->and($disabled->thresholdSeconds)->toBe(0);
});

it('keeps oldest ready job age optional and clamps future timestamps', function (): void {
    $waitThreshold = new QueueWaitThreshold(app(Repository::class));

    expect($waitThreshold->forTarget('redis', 'custom', 5, null)->oldestReadyAgeSeconds)->toBeNull()
        ->and($waitThreshold->forTarget('redis', 'custom', 5, 1900)->oldestReadyAgeSeconds)->toBe(0);
});

it('keeps the threshold unclassified until the wait can be calculated', function (): void {
    $waitThreshold = new QueueWaitThreshold(app(Repository::class));

    expect($waitThreshold->forTarget('redis', 'custom', null, 1740)->toArray())->toBe([
        'connection' => 'redis',
        'status' => 'calculating',
        'monitored' => true,
        'waitSeconds' => null,
        'thresholdSeconds' => 30,
        'oldestReadyAgeSeconds' => 60,
    ]);
});

it('summarizes the most exceeded target separately from the oldest ready job', function (): void {
    $waitThreshold = new QueueWaitThreshold(app(Repository::class));

    $summary = $waitThreshold->summarize([
        $waitThreshold->forTarget('redis', 'unconfigured', 65, 1500),
        $waitThreshold->forTarget('sqs', 'reports', 125, 1680),
    ]);

    expect($summary->toArray())->toBe([
        'status' => QueueWaitThresholdStatus::Exceeded->value,
        'decisiveConnection' => 'sqs',
        'waitSeconds' => 125,
        'thresholdSeconds' => 100,
        'oldestReadyAgeSeconds' => 300,
        'oldestReadyConnection' => 'redis',
        'targets' => [
            [
                'connection' => 'redis',
                'status' => QueueWaitThresholdStatus::Exceeded->value,
                'monitored' => true,
                'waitSeconds' => 65,
                'thresholdSeconds' => 60,
                'oldestReadyAgeSeconds' => 300,
            ],
            [
                'connection' => 'sqs',
                'status' => QueueWaitThresholdStatus::Exceeded->value,
                'monitored' => true,
                'waitSeconds' => 125,
                'thresholdSeconds' => 100,
                'oldestReadyAgeSeconds' => 120,
            ],
        ],
    ]);
});

it('selects the closest monitored threshold and otherwise the largest disabled wait', function (): void {
    $waitThreshold = new QueueWaitThreshold(app(Repository::class));

    $withinBounds = $waitThreshold->summarize([
        $waitThreshold->forTarget('redis', 'unconfigured', 10, null),
        $waitThreshold->forTarget('sqs', 'custom', 22, null),
        $waitThreshold->forTarget('database', 'disabled', 200, null),
    ]);

    $disabled = $waitThreshold->summarize([
        $waitThreshold->forTarget('redis', 'disabled', 4, null),
        $waitThreshold->forTarget('sqs', 'disabled', 8, null),
    ]);

    expect($withinBounds->status)->toBe(QueueWaitThresholdStatus::WithinBounds)
        ->and($withinBounds->decisiveConnection)->toBe('sqs')
        ->and($withinBounds->waitSeconds)->toBe(22)
        ->and($withinBounds->thresholdSeconds)->toBe(60)
        ->and($disabled->status)->toBe(QueueWaitThresholdStatus::Disabled)
        ->and($disabled->decisiveConnection)->toBe('sqs')
        ->and($disabled->waitSeconds)->toBe(8);
});

it('requires at least one queue target to summarize', function (): void {
    (new QueueWaitThreshold(app(Repository::class)))->summarize([]);
})->throws(InvalidArgumentException::class);
