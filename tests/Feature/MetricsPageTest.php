<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Horizon;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Horizon::auth(static fn (): bool => true);

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

describe('metrics pages', function (): void {
    it('routes the metrics entry point to job metrics', function (): void {
        get('/horizon/metrics')
            ->assertRedirect('/horizon/metrics/jobs');
    });

    it('renders the sortable job metrics index through its dedicated route', function (): void {
        $repository = mockDashboardContract(MetricsRepository::class);
        dashboardReturns($repository, 'measuredJobs', ['App\\Jobs\\ProcessPayment']);
        dashboardReturnsFor($repository, 'throughputForJob', ['App\\Jobs\\ProcessPayment'], 72);
        dashboardReturnsFor($repository, 'runtimeForJob', ['App\\Jobs\\ProcessPayment'], 1240.0);
        app()->instance(MetricsData::class, new MetricsData($repository));

        get('/horizon/metrics/jobs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Metrics/Index')
                ->where('meta.title', 'Metrics')
                ->where('meta.activeNavigation', 'metrics')
                ->where('type', 'jobs')
                ->where('metrics.data.0.name', 'App\\Jobs\\ProcessPayment')
                ->where('metrics.data.0.throughput', 72)
                ->where('metrics.data.0.runtime', 1.24));
    });

    it('renders a queue metric preview with normalized snapshots', function (): void {
        $repository = mockDashboardContract(MetricsRepository::class);
        dashboardReturnsFor($repository, 'snapshotsForQueue', ['emails'], [
            (object) ['time' => 1784387100, 'throughput' => 19, 'runtime' => 2500],
            (object) ['time' => 1784387400, 'throughput' => null, 'runtime' => null],
        ]);
        app()->instance(MetricsData::class, new MetricsData($repository));

        get('/horizon/metrics/queues/emails')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Metrics/Show')
                ->where('meta.title', 'Metrics for emails')
                ->where('meta.activeNavigation', 'metrics')
                ->where('type', 'queues')
                ->where('name', 'emails')
                ->where('preview.data.0.timestamp', 1784387100)
                ->where('preview.data.0.throughput', 19)
                ->where('preview.data.0.runtime', 2.5)
                ->where('preview.data.1.timestamp', 1784387400)
                ->where('preview.data.1.throughput', 0)
                ->where('preview.data.1.runtime', null));
    });

    it('rejects metric types outside the route constraint', function (): void {
        get('/horizon/metrics/workers')->assertNotFound();
    });
});
