<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThresholdStatus;

it('classifies waits against the effective Horizon threshold', function (): void {
    config()->set('horizon.waits', [
        'redis:within' => 30,
        'redis:exceeded' => 30,
        'redis:disabled' => 0,
    ]);

    $threshold = new QueueWaitThreshold(app(Repository::class));

    expect($threshold->forTarget('redis', 'within', 30, null)->status)
        ->toBe(QueueWaitThresholdStatus::WithinBounds)
        ->and($threshold->forTarget('redis', 'exceeded', 31, null)->status)
        ->toBe(QueueWaitThresholdStatus::Exceeded)
        ->and($threshold->forTarget('redis', 'disabled', 999, null)->status)
        ->toBe(QueueWaitThresholdStatus::Disabled);
});
