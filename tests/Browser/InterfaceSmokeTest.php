<?php

declare(strict_types=1);

use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserPageFixtures;

describe('Horizon interface', function (): void {
    it('renders the dashboard in a real browser', function (): void {
        $pages = visit([
            '/horizon',
            '/horizon/dashboard',
        ]);

        $pages
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs()
            ->assertNoAccessibilityIssues();

        [$dashboard, $dashboardAlias] = $pages;

        $dashboard->assertSee('Dashboard');
        $dashboardAlias->assertSee('Dashboard');
    });

    it('follows the metrics entry point to the rendered job metrics page', function (): void {
        visit('/horizon/metrics')
            ->assertPathIs('/horizon/metrics/jobs')
            ->assertSee('Job Metrics')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs()
            ->assertNoAccessibilityIssues();
    });

    it('renders every top-level feature surface without browser errors', function (): void {
        $pages = visit([
            '/horizon/instances',
            '/horizon/monitoring',
            '/horizon/metrics/jobs',
            '/horizon/metrics/queues',
            '/horizon/batches',
            '/horizon/queues',
            '/horizon/jobs/pending',
            '/horizon/jobs/completed',
            '/horizon/jobs/silenced',
            '/horizon/failed',
        ]);

        $pages
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs()
            ->assertNoAccessibilityIssues();

        [$instances, $monitoring, $jobMetrics, $queueMetrics, $batches, $queues, $pending, $completed, $silenced, $failed] = $pages;

        $instances->assertSee('Instances');
        $monitoring->assertSee('Monitoring');
        $jobMetrics->assertSee('Job Metrics');
        $queueMetrics->assertSee('Queue Metrics');
        $batches->assertSee('Batches');
        $queues->assertSee('Queues');
        $pending->assertSee('Pending Jobs');
        $completed->assertSee('Completed Jobs');
        $silenced->assertSee('Silenced Jobs');
        $failed->assertSee('Failed Jobs');
    });

    it('renders every detail page surface without browser errors', function (): void {
        bindBrowserPageFixtures();

        $pages = visit([
            '/horizon/supervisors/horizon-web-01%3Asupervisor-1',
            '/horizon/monitoring/checkout/jobs',
            '/horizon/monitoring/checkout/failed',
            '/horizon/metrics/jobs/App%5CJobs%5CImportFeed',
            '/horizon/metrics/queues/emails',
            '/horizon/batches/batch-1',
            '/horizon/queues/reports',
            '/horizon/jobs/pending/pending-1',
            '/horizon/jobs/completed/completed-1',
            '/horizon/jobs/silenced/silenced-1',
            '/horizon/failed/failed-1',
        ]);

        $pages
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs()
            ->assertNoAccessibilityIssues();

        [
            $supervisor,
            $recentMonitoring,
            $failedMonitoring,
            $jobMetric,
            $queueMetric,
            $batch,
            $queue,
            $pendingJob,
            $completedJob,
            $silencedJob,
            $failedJob,
        ] = $pages;

        $supervisor->assertSee('supervisor-1');
        $recentMonitoring->assertSee('checkout');
        $failedMonitoring->assertSee('checkout');
        $jobMetric->assertSee('Throughput — App\\Jobs\\ImportFeed');
        $queueMetric->assertSee('Throughput — emails');
        $batch->assertSee('Import customer records');
        $queue->assertSee('reports');
        $pendingJob->assertSee('App\\Jobs\\ImportFeed');
        $completedJob->assertSee('App\\Jobs\\ImportFeed');
        $silencedJob->assertSee('App\\Jobs\\ImportFeed');
        $failedJob->assertSee('App\\Jobs\\ImportFeed');
    });
});
