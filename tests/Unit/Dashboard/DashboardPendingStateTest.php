<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use NckRtl\HorizonNewDawn\Dashboard\DashboardPendingState;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

it('deduplicates supervised queues and totals their redis states', function (): void {
    $connection = mockDashboardContract(Queue::class);
    dashboardReturnsFor($connection, 'reservedSize', ['default'], 2);
    dashboardReturnsFor($connection, 'readyNow', ['default'], 5);
    dashboardReturnsFor($connection, 'delayedSize', ['default'], 7);
    dashboardReturnsFor($connection, 'reservedSize', ['emails'], 1);
    dashboardReturnsFor($connection, 'readyNow', ['emails'], 3);
    dashboardReturnsFor($connection, 'delayedSize', ['emails'], 4);

    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsFor($queues, 'connection', ['redis'], $connection);

    expect((new DashboardPendingState($queues))->forQueues([
        'redis:default,emails' => 3,
        'redis:default' => 1,
    ])->toArray())->toBe([
        'reserved' => 3,
        'readyNow' => 8,
        'delayed' => 11,
    ]);
});

it('isolates queue transport failures', function (): void {
    $queues = mockDashboardContract(QueueFactory::class);
    dashboardThrows($queues, 'connection', new RuntimeException('redis unavailable'));

    expect((new DashboardPendingState($queues))->forQueues([
        'redis:default' => 1,
    ])->toArray())->toBe([
        'reserved' => null,
        'readyNow' => null,
        'delayed' => null,
    ]);
});
