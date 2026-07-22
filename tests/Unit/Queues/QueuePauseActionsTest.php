<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Queue\QueueManager;
use NckRtl\HorizonNewDawn\Queues\Actions\PauseQueue;
use NckRtl\HorizonNewDawn\Queues\Actions\ResumeQueue;
use NckRtl\HorizonNewDawn\Queues\Data\PauseQueueData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;

beforeEach(function (): void {
    requireQueuePausing();

    app(CacheFactory::class)->store()->clear();
    CarbonImmutable::setTestNow('2026-07-20 18:00:00 UTC');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

describe('queue pause actions', function (): void {
    it('pauses a queue indefinitely and clears an earlier deadline', function (): void {
        $metadata = new QueuePauseMetadata(app(CacheFactory::class));
        $metadata->storeUntil('redis', 'reports', CarbonImmutable::now()->addHour());
        $queues = app(QueueManager::class);

        $deadline = (new PauseQueue($queues, $metadata))->handle(
            new PauseQueueData('redis', 'reports', null),
        );

        expect($deadline)->toBeNull()
            ->and($queues->isPaused('redis', 'reports'))->toBeTrue()
            ->and($metadata->pausedUntil('redis', 'reports'))->toBeNull();
    });

    it('sets a timed pause from now and replaces the readable deadline', function (): void {
        $metadata = new QueuePauseMetadata(app(CacheFactory::class));
        $queues = app(QueueManager::class);
        $expectedDeadline = CarbonImmutable::now()->addHour();

        $deadline = (new PauseQueue($queues, $metadata))->handle(
            new PauseQueueData('redis', 'reports', 60),
        );

        expect($deadline?->equalTo($expectedDeadline))->toBeTrue()
            ->and($queues->isPaused('redis', 'reports'))->toBeTrue()
            ->and($metadata->pausedUntil('redis', 'reports'))->toBe($expectedDeadline->timestamp);
    });

    it('resumes a queue and removes its readable deadline', function (): void {
        $metadata = new QueuePauseMetadata(app(CacheFactory::class));
        $metadata->storeUntil('redis', 'reports', CarbonImmutable::now()->addHour());
        $queues = app(QueueManager::class);
        $queues->pause('redis', 'reports');

        (new ResumeQueue($queues, $metadata))->handle('redis', 'reports');

        expect($queues->isPaused('redis', 'reports'))->toBeFalse()
            ->and($metadata->pausedUntil('redis', 'reports'))->toBeNull();
    });
});
