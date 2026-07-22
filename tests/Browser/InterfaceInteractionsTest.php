<?php

declare(strict_types=1);

use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserPageFixtures;

describe('Horizon interface interactions', function (): void {
    beforeEach(function (): void {
        bindBrowserPageFixtures();
    });

    it('offers scoped bulk job actions without duplicating filter controls', function (): void {
        visit('/horizon/jobs/pending')
            ->click('button[aria-label="Pending jobs actions"]')
            ->assertSee('Cancel all ready jobs')
            ->assertSee('Cancel all delayed jobs')
            ->assertSee('Cancel all pending jobs')
            ->assertDontSee('Clear all filters')
            ->click('Cancel all delayed jobs')
            ->assertSee('Cancel all delayed jobs?')
            ->assertSee('Ready, reserved, and running jobs will not be affected.')
            ->click('[data-test="confirm-cancel-pending-jobs"]')
            ->assertSee('Cancelled 0 delayed jobs.');

        visit('/horizon/failed')
            ->click('button[aria-label="Failed jobs actions"]')
            ->assertSee('Clear all failed jobs')
            ->assertDontSee('Clear all filters');

        visit('/horizon/jobs/completed')
            ->assertMissing('button[aria-label="Completed jobs actions"]')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('cancels a pending job before a worker reserves it', function (): void {
        visit('/horizon/jobs/pending/pending-1')
            ->click('button[aria-label="Pending job actions"]')
            ->click('Cancel job')
            ->assertSee('Cancel this job?')
            ->click('[data-test="confirm-cancel-job"]')
            ->assertPathIs('/horizon/jobs/pending')
            ->assertSee('Job cancelled.')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('preserves interface preferences across Inertia navigation', function (): void {
        $page = visit('/horizon');

        $page
            ->click('[aria-label^="Color scheme:"]')
            ->assertScript('document.documentElement.classList.contains("dark")')
            ->click('[aria-label="Auto load new entries"]')
            ->assertAttribute('[aria-label="Auto load new entries"]', 'aria-pressed', 'true')
            ->click('Monitoring')
            ->assertPathIs('/horizon/monitoring')
            ->assertAttribute('[aria-label="Auto load new entries"]', 'aria-pressed', 'true')
            ->assertScript('document.documentElement.classList.contains("dark")')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('navigates through the mobile sidebar', function (): void {
        visit('/horizon')
            ->on()->iPhone14Pro()
            ->click('Toggle Sidebar')
            ->click('Queues')
            ->assertPathIs('/horizon/queues')
            ->assertSee('Queues')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('searches jobs and exposes the feature filters', function (): void {
        visit('/horizon/jobs/completed')
            ->fill('input[aria-label="Search completed jobs"]', 'a job that does not exist')
            ->assertValue('input[aria-label="Search completed jobs"]', 'a job that does not exist')
            ->assertSee('No matching completed jobs')
            ->click('button[aria-label="Filter jobs"]')
            ->assertSee('Narrow the loaded completed jobs using filters available for this tab.')
            ->assertSee('Job class')
            ->assertSee('Queue')
            ->assertSee('Connection')
            ->assertSee('Tag')
            ->click('Done')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('searches and filters metrics and queues', function (): void {
        $metrics = visit('/horizon/metrics/jobs');

        $metrics
            ->assertScript('(function () { const list = document.querySelector(\'[role="tablist"]\'); const active = document.querySelector(\'[role="tab"][aria-selected="true"]\'); if (!list || !active) { return false; } const listRect = list.getBoundingClientRect(); const activeRect = active.getBoundingClientRect(); return listRect.top <= activeRect.top && listRect.bottom >= activeRect.bottom; })()')
            ->fill('input[aria-label="Search job metrics"]', 'mail')
            ->assertValue('input[aria-label="Search job metrics"]', 'mail')
            ->assertSee('No matching job metrics')
            ->click('button[aria-label="Filter job metrics"]')
            ->assertSee('Throughput')
            ->assertSee('Runtime')
            ->click('Done')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();

        visit('/horizon/queues')
            ->fill('input[aria-label="Search queues"]', 'a queue that does not exist')
            ->assertValue('input[aria-label="Search queues"]', 'a queue that does not exist')
            ->assertSee('No matching queues')
            ->click('button[aria-label="Filter queues"]')
            ->assertSee('Status')
            ->assertSee('Wait threshold')
            ->click('Done')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('opens the monitor tag workflow without submitting it', function (): void {
        visit('/horizon/monitoring')
            ->click('button[aria-label="Monitor Tag"]')
            ->click('Create tag')
            ->assertSee('Monitor New Tag')
            ->fill('#monitoring-tag', 'App\\Models\\User:6352')
            ->assertValue('#monitoring-tag', 'App\\Models\\User:6352')
            ->click('Cancel')
            ->assertDontSee('Monitor New Tag')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('searches, filters, and changes batch status', function (): void {
        visit('/horizon/batches')
            ->fill('input[aria-label="Search batches by name or ID"]', 'a batch that does not exist')
            ->assertValue(
                'input[aria-label="Search batches by name or ID"]',
                'a batch that does not exist',
            )
            ->click('button[aria-label="Filter batches"]')
            ->assertSee('Narrow retained batches by queue, connection, or creation time.')
            ->assertSee('Queue')
            ->assertSee('Connection')
            ->assertSee('Created')
            ->click('Done')
            ->assertDontSee('Narrow retained batches by queue, connection, or creation time.')
            ->click('[role="tab"]:nth-child(2)')
            ->assertAttribute('[role="tab"]:nth-child(2)', 'aria-selected', 'true')
            ->assertSee('No matching batches')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('shows one batch failure row per retry lineage with total attempts', function (): void {
        visit('/horizon/batches/batch-1')
            ->assertSee('Failed Jobs')
            ->assertSee('Attempts')
            ->assertSee('3')
            ->assertPresent('[data-test="batch-failed-job-row"]')
            ->assertCount('[data-test="batch-failed-job-row"]', 1)
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('searches failed jobs by exact tag and exposes its filters', function (): void {
        visit('/horizon/failed')
            ->fill('input[aria-label="Filter failed jobs by exact tag"]', 'tenant:missing')
            ->assertValue('input[aria-label="Filter failed jobs by exact tag"]', 'tenant:missing')
            ->assertSee('No matching failed jobs')
            ->click('button[aria-label="Filter jobs"]')
            ->assertSee('Narrow the loaded failed jobs using filters available for this tab.')
            ->assertSee('Connection')
            ->assertSee('Queue')
            ->assertSee('Retry status')
            ->click('Done')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });
});
