<?php

declare(strict_types=1);

use Laravel\Horizon\Contracts\MetricsRepository;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Metrics\MetricType;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('MetricsData', function (): void {
    it('sorts job metrics and normalizes throughput and runtime', function (): void {
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturns($metrics, 'measuredJobs', ['App\\Jobs\\SyncInventory', '', 'App\\Jobs\\ProcessPayment']);
        dashboardReturnsFor($metrics, 'throughputForJob', ['App\\Jobs\\SyncInventory'], 31);
        dashboardReturnsFor($metrics, 'runtimeForJob', ['App\\Jobs\\SyncInventory'], 1250.6);
        dashboardReturnsFor($metrics, 'throughputForJob', ['App\\Jobs\\ProcessPayment'], 72);
        dashboardReturnsFor($metrics, 'runtimeForJob', ['App\\Jobs\\ProcessPayment'], 83.4);

        $page = (new MetricsData($metrics))->index(MetricType::Jobs);

        expect($page->available)->toBeTrue()
            ->and(array_map(static fn ($row): string => $row->name, $page->metrics))
            ->toBe(['App\\Jobs\\ProcessPayment', 'App\\Jobs\\SyncInventory'])
            ->and($page->metrics[0]->throughput)->toBe(72)
            ->and($page->metrics[0]->runtime)->toBe(0.083)
            ->and($page->metrics[1]->runtime)->toBe(1.251);
    });

    it('reads queue metrics through the queue repository boundary', function (): void {
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturns($metrics, 'measuredQueues', ['emails']);
        dashboardReturnsFor($metrics, 'throughputForQueue', ['emails'], 19);
        dashboardReturnsFor($metrics, 'runtimeForQueue', ['emails'], 2500.0);

        $page = (new MetricsData($metrics))->index(MetricType::Queues);

        expect($page->metrics)->toHaveCount(1)
            ->and($page->metrics[0]->name)->toBe('emails')
            ->and($page->metrics[0]->throughput)->toBe(19)
            ->and($page->metrics[0]->runtime)->toBe(2.5);
    });

    it('preserves valid snapshots and sorts them chronologically', function (): void {
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($metrics, 'snapshotsForJob', ['App\\Jobs\\ProcessPayment'], [
            (object) ['time' => 1784387400, 'throughput' => 18, 'runtime' => 2120.8],
            ['time' => '1784387100', 'throughput' => '12', 'runtime' => '1500'],
            ['time' => '1784387130', 'throughput' => '5', 'runtime' => '500'],
            (object) ['time' => 1784387200, 'throughput' => 14],
            'invalid',
        ]);

        $preview = (new MetricsData($metrics))->preview(
            MetricType::Jobs,
            'App\\Jobs\\ProcessPayment',
        );

        expect($preview->available)->toBeTrue()
            ->and($preview->name)->toBe('App\\Jobs\\ProcessPayment')
            ->and(array_map(static fn ($snapshot): int => $snapshot->timestamp, $preview->snapshots))
            ->toBe([1784387100, 1784387130, 1784387200, 1784387400])
            ->and($preview->snapshots[0]->throughput)->toBe(12)
            ->and($preview->snapshots[0]->runtime)->toBe(1.5)
            ->and($preview->snapshots[1]->throughput)->toBe(5)
            ->and($preview->snapshots[1]->runtime)->toBe(0.5)
            ->and($preview->snapshots[2]->throughput)->toBe(14)
            ->and($preview->snapshots[2]->runtime)->toBeNull()
            ->and($preview->snapshots[3]->runtime)->toBe(2.121);
    });

    it('keeps quiet snapshot timestamps without inventing a runtime observation', function (): void {
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($metrics, 'snapshotsForQueue', ['default'], [
            (object) ['time' => 1784387300, 'throughput' => null, 'runtime' => null],
        ]);

        $preview = (new MetricsData($metrics))->preview(MetricType::Queues, 'default');

        expect($preview->snapshots)->toHaveCount(1)
            ->and($preview->snapshots[0]->timestamp)->toBe(1784387300)
            ->and($preview->snapshots[0]->throughput)->toBe(0)
            ->and($preview->snapshots[0]->runtime)->toBeNull();
    });

    it('returns an available empty preview when no snapshots exist', function (): void {
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($metrics, 'snapshotsForQueue', ['default'], []);

        $preview = (new MetricsData($metrics))->preview(MetricType::Queues, 'default');

        expect($preview->available)->toBeTrue()
            ->and($preview->snapshots)->toBe([])
            ->and($preview->message)->toBeNull();
    });

    it('returns explicit unavailable states without leaking repository errors', function (): void {
        $metrics = mockDashboardContract(MetricsRepository::class);
        dashboardThrows($metrics, 'measuredJobs', new RuntimeException('redis password leaked'));

        $page = (new MetricsData($metrics))->index(MetricType::Jobs);

        expect($page->available)->toBeFalse()
            ->and($page->metrics)->toBe([])
            ->and($page->message)->toBe('Job metrics are currently unavailable.')
            ->and($page->message)->not->toContain('password');
    });
});
