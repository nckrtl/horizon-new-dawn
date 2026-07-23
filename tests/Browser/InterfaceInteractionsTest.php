<?php

declare(strict_types=1);

use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserPageFixtures;
use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserSupervisorScalingFixtures;

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

    it('keeps predicted autoscaling active until the process count reaches its target', function (): void {
        bindBrowserSupervisorScalingFixtures();

        $page = visit('/horizon/instances')->assertSee('Instances');
        $autoRefreshWasEnabled = $page->script(
            '() => document.querySelector(\'[aria-label="Auto load new entries"]\')?.getAttribute(\'aria-pressed\') === \'true\'',
        );

        if (! $autoRefreshWasEnabled) {
            $page->click('[aria-label="Auto load new entries"]');
        }

        $page->assertPresent('[data-scaling-state="up"]');

        $meters = $page->script(<<<'JS'
            () => {
                const meters = {}

                for (const state of ['up']) {
                    const meter = document.querySelector(`[data-scaling-state="${state}"]`)
                    const cell = meter?.closest('td')
                    const track = meter?.querySelector('[data-scaling-track]')
                    const processCount = cell?.querySelector('[data-process-count]')
                    const blocks = Array.from(meter?.querySelectorAll('[data-scaling-block]') ?? [])
                    const meterRect = meter?.getBoundingClientRect()
                    const trackRect = track?.getBoundingClientRect()
                    const processCountRect = processCount?.getBoundingClientRect()
                    const blockRects = blocks.map((block) => block.getBoundingClientRect())

                    meters[state] = {
                        blockCount: blocks.length,
                        blockHeights: [...new Set(blocks.map((block) => getComputedStyle(block).height))],
                        blockWidths: [...new Set(blocks.map((block) => getComputedStyle(block).width))],
                        blocksDoNotFlex: blocks.every((block) => {
                            const styles = getComputedStyle(block)

                            return styles.flexGrow === '0' && styles.flexShrink === '0'
                        }),
                        blocksDoNotTransform: [...new Set(blocks.map((block) => getComputedStyle(block).transform))],
                        vertical: new Set(blockRects.map((block) => Math.round(block.left))).size === 1
                            && new Set(blockRects.map((block) => Math.round(block.top))).size === blocks.length,
                        blockGaps: [...new Set(blockRects.slice(1).map(
                            (block, index) => Math.round(block.top - blockRects[index].bottom),
                        ))],
                        gapFromCount: Math.round((trackRect?.left ?? 0) - (processCountRect?.right ?? 0)),
                        trackFillsMeter: Math.abs((trackRect?.height ?? 0) - (meterRect?.height ?? 0)) < 0.5,
                        topOffset: meter ? getComputedStyle(meter).top : '',
                        bottomOffset: meter ? getComputedStyle(meter).bottom : '',
                        animationNames: blocks.map((block) => getComputedStyle(block).animationName),
                        animationDelays: [...new Set(blocks.map((block) => getComputedStyle(block).animationDelay))],
                        animationDurations: [...new Set(blocks.map((block) => getComputedStyle(block).animationDuration))],
                        blockOpacities: [...new Set(blocks.map((block) => getComputedStyle(block).opacity))],
                        transitionDurations: [...new Set(blocks.map((block) => getComputedStyle(block).transitionDuration))],
                    }
                }

                return meters
            }
        JS);

        expect($meters)->toBe([
            'up' => [
                'blockCount' => 5,
                'blockHeights' => ['3px'],
                'blockWidths' => ['8px'],
                'blocksDoNotFlex' => true,
                'blocksDoNotTransform' => ['none'],
                'vertical' => true,
                'blockGaps' => [1],
                'gapFromCount' => 8,
                'trackFillsMeter' => true,
                'topOffset' => '4px',
                'bottomOffset' => '4px',
                'animationNames' => ['none', 'none', 'none', 'none', 'none'],
                'animationDelays' => ['0s'],
                'animationDurations' => ['0s'],
                'blockOpacities' => ['1'],
                'transitionDurations' => ['0s'],
            ],
        ]);

        $animationRepeated = $page->script(<<<'JS'
            () => new Promise((resolve, reject) => {
                const meter = document.querySelector('[data-scaling-state="up"]')
                let sawFullMeter = false
                const timeout = window.setTimeout(
                    () => finish(new Error('Timed out waiting for the scaling animation to repeat.')),
                    6000,
                )
                const observer = new MutationObserver(inspect)

                function finish(error = null) {
                    window.clearTimeout(timeout)
                    observer.disconnect()

                    if (error) {
                        reject(error)
                        return
                    }

                    resolve(true)
                }

                function inspect() {
                    const filledBlocks = meter?.getAttribute('data-scaling-filled')

                    if (filledBlocks === '5') {
                        sawFullMeter = true
                    } else if (sawFullMeter && filledBlocks === '1') {
                        finish()
                    }
                }

                if (! meter) {
                    finish(new Error('The scaling indicator is missing.'))
                    return
                }

                observer.observe(meter, {
                    attributes: true,
                    attributeFilter: ['data-scaling-filled'],
                })
                inspect()
            })
        JS);

        expect($animationRepeated)->toBeTrue();

        $page
            ->assertAriaAttribute(
                '[data-scaling-state="up"]',
                'label',
                'Scaling up from 6 processes to 7 processes. Time-based autoscaling with 4 ready jobs.',
            )
            ->hover('[data-scaling-state="up"]')
            ->assertSee('Scaling up from 6 processes to 7 processes')
            ->click('[aria-label="Auto load new entries"]')
            ->assertMissing('[data-scaling-state]');

        if ($autoRefreshWasEnabled) {
            $page->click('[aria-label="Auto load new entries"]');
        }

        $page
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs()
            ->assertNoAccessibilityIssues();
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

    it('keeps queue actions usable without unsupported pause controls', function (): void {
        visit('/horizon/queues/reports')
            ->click('button[aria-label="Queue actions for reports"]')
            ->assertSee('Clear queue')
            ->assertDontSee('Pause')
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
