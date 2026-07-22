<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Dashboard\DashboardPendingState;
use NckRtl\HorizonNewDawn\Dashboard\HorizonStatus;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('HorizonRuntime', function (): void {
    it('reports inactive when no masters are registered', function (): void {
        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardReturns($masters, 'all', []);

        expect((new HorizonRuntime($masters))->status())->toBe(HorizonStatus::Inactive);
    });

    it('reports paused when every master is paused', function (): void {
        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardReturns($masters, 'all', [
            (object) ['status' => 'paused'],
            (object) ['status' => 'paused'],
        ]);

        expect((new HorizonRuntime($masters))->status())->toBe(HorizonStatus::Paused);
    });

    it('reports running when at least one master is running', function (): void {
        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardReturns($masters, 'all', [
            (object) ['status' => 'paused'],
            (object) ['status' => 'running'],
        ]);

        expect((new HorizonRuntime($masters))->status())->toBe(HorizonStatus::Running);
    });

    it('returns unavailable without exposing repository failures', function (): void {
        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        dashboardThrows($masters, 'all', new RuntimeException('redis-password'));

        expect((new HorizonRuntime($masters))->status())->toBe(HorizonStatus::Unavailable);
    });

    it('reports idle when processes are active without pending jobs', function (): void {
        expect(runtimeForPendingState(processes: 3)->isProcessing(HorizonStatus::Running))->toBeFalse();
    });

    it('reports idle when only delayed jobs are pending', function (): void {
        expect(runtimeForPendingState(delayed: 3, processes: 3)->isProcessing(HorizonStatus::Running))
            ->toBeFalse();
    });

    it('reports processing when pending jobs and active processes overlap', function (): void {
        expect(runtimeForPendingState(readyNow: 2, processes: 3)->isProcessing(HorizonStatus::Running))
            ->toBeTrue();
    });

    it('reports processing when a job is reserved and the ready queue is empty', function (): void {
        expect(runtimeForPendingState(reserved: 1, processes: 3)->isProcessing(HorizonStatus::Running))
            ->toBeTrue();
    });

    it('resolves the processing dependencies from the application container', function (): void {
        $masters = mockDashboardContract(MasterSupervisorRepository::class);
        $workload = mockDashboardContract(WorkloadRepository::class);
        dashboardReturns($workload, 'get', [
            [
                'name' => 'default',
                'length' => 2,
                'wait' => 0,
                'processes' => 3,
                'split_queues' => null,
            ],
        ]);

        $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
        dashboardReturns($waitTimes, 'calculate', ['redis:default' => 0]);

        $queue = mockDashboardContract(Queue::class);
        dashboardReturns($queue, 'reservedSize', 0);
        dashboardReturns($queue, 'readyNow', 2);
        dashboardReturns($queue, 'delayedSize', 0);
        $queues = mockDashboardContract(QueueFactory::class);
        dashboardReturns($queues, 'connection', $queue);

        app()->instance(MasterSupervisorRepository::class, $masters);
        app()->instance(WorkloadRepository::class, $workload);
        app()->instance(WaitTimeCalculator::class, $waitTimes);
        app()->instance(QueueFactory::class, $queues);

        expect(app(HorizonRuntime::class)->isProcessing(HorizonStatus::Running))->toBeTrue();
    });

    it('does not report processing without an active process', function (): void {
        expect(runtimeForPendingState(readyNow: 2)->isProcessing(HorizonStatus::Running))->toBeFalse();
    });

    it('does not report processing when Horizon is not running', function (): void {
        $masters = mockDashboardContract(MasterSupervisorRepository::class);

        expect((new HorizonRuntime($masters))->isProcessing(HorizonStatus::Paused))->toBeFalse();
    });
});

function runtimeForPendingState(
    int $reserved = 0,
    int $readyNow = 0,
    int $delayed = 0,
    int $processes = 0,
): HorizonRuntime {
    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    $workload = mockDashboardContract(WorkloadRepository::class);
    dashboardReturns($workload, 'get', [
        [
            'name' => 'default',
            'length' => $readyNow,
            'wait' => 0,
            'processes' => $processes,
            'split_queues' => null,
        ],
    ]);

    $waitTimes = mockDashboardContract(WaitTimeCalculator::class);
    dashboardReturns($waitTimes, 'calculate', ['redis:default' => 0]);

    $queue = mockDashboardContract(Queue::class);
    dashboardReturns($queue, 'reservedSize', $reserved);
    dashboardReturns($queue, 'readyNow', $readyNow);
    dashboardReturns($queue, 'delayedSize', $delayed);
    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturns($queues, 'connection', $queue);

    return new HorizonRuntime(
        $masters,
        $workload,
        new DashboardPendingState($queues),
        $waitTimes,
    );
}
