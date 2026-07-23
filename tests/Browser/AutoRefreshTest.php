<?php

declare(strict_types=1);

use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserInfiniteScrollRefreshFixtures;
use function NckRtl\HorizonNewDawn\Tests\Support\bindBrowserPageFixtures;

describe('automatic refresh', function (): void {
    it('intercepts asset-version changes in the rendered interface', function (): void {
        $page = visit('/horizon/jobs/pending');

        $defaultPrevented = $page->script(<<<'JS'
            () => {
                const event = new CustomEvent('inertia:location', {
                    cancelable: true,
                    detail: {
                        url: new URL(window.location.href),
                        versionChange: true,
                    },
                })

                document.dispatchEvent(event)

                return event.defaultPrevented
            }
        JS);

        expect($defaultPrevented)->toBeTrue();

        $page
            ->assertPathIs('/horizon/jobs/pending')
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('polls infinite-scroll job data as authoritative state', function (): void {
        $page = visit('/horizon/jobs/pending')
            ->assertPresent('[aria-label="Auto load new entries"]');

        $pollMetadata = $page->script(<<<'JS'
            () => new Promise((resolve, reject) => {
                let mergeIntent = null
                let reset = null
                let pressedBeforeEnable = null
                let startedAt = null
                const setRequestHeader = XMLHttpRequest.prototype.setRequestHeader
                const timeout = window.setTimeout(() => {
                    document.removeEventListener('inertia:success', onSuccess)
                    XMLHttpRequest.prototype.setRequestHeader = setRequestHeader
                    reject(new Error('Timed out waiting for the automatic refresh request.'))
                }, 10000)

                function onSuccess(event) {
                    if (event.detail.page.component !== 'Jobs/Index') {
                        return
                    }

                    if (mergeIntent === null && reset === null) {
                        return
                    }

                    window.clearTimeout(timeout)
                    document.removeEventListener('inertia:success', onSuccess)
                    XMLHttpRequest.prototype.setRequestHeader = setRequestHeader

                    resolve({
                        mergeIntent,
                        reset,
                        pressedBeforeEnable,
                        elapsedMilliseconds: startedAt === null ? null : Date.now() - startedAt,
                    })
                }

                const toggle = document.querySelector('[aria-label="Auto load new entries"]')

                function beginObservedPoll() {
                    XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
                        if (name.toLowerCase() === 'x-inertia-reset') {
                            reset = value
                        }

                        if (name.toLowerCase() === 'x-inertia-infinite-scroll-merge-intent') {
                            mergeIntent = value
                        }

                        return setRequestHeader.call(this, name, value)
                    }

                    document.addEventListener('inertia:success', onSuccess)
                    startedAt = Date.now()
                    pressedBeforeEnable = toggle?.getAttribute('aria-pressed')
                    toggle?.click()
                }

                if (toggle?.getAttribute('aria-pressed') === 'true') {
                    toggle.click()
                    window.requestAnimationFrame(() => window.requestAnimationFrame(beginObservedPoll))
                } else {
                    beginObservedPoll()
                }
            })
        JS);

        expect($pollMetadata)->toMatchArray([
            'mergeIntent' => null,
            'reset' => 'jobs',
            'pressedBeforeEnable' => 'false',
        ])->and($pollMetadata['elapsedMilliseconds'])->toBeLessThan(1000);

        $page
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('keeps loaded infinite-scroll rows mounted while polling updates the first page', function (): void {
        bindBrowserInfiniteScrollRefreshFixtures();

        $page = visit('/horizon/failed?starting_at=-1');

        $result = $page->script(<<<'JS'
            () => new Promise((resolve, reject) => {
                const dataRows = () => Array.from(document.querySelectorAll('main tbody tr'))
                    .filter((row) => row.querySelector('a[href*="/failed/"]') !== null)
                let loadedRowCount = null
                const timeout = window.setTimeout(
                    () => finish(new Error('Timed out waiting for the refreshed failed jobs.')),
                    10000,
                )
                const observer = new MutationObserver(inspect)

                function finish(error = null, value = null) {
                    window.clearTimeout(timeout)
                    document.removeEventListener('inertia:success', inspect)
                    observer.disconnect()

                    if (error) {
                        reject(error)
                        return
                    }

                    resolve(value)
                }

                function inspect() {
                    const rows = dataRows()
                    const firstJob = rows[0]?.querySelector('a[href*="/failed/"]')

                    if (loadedRowCount === null && rows.length === 100) {
                        loadedRowCount = rows.length
                        window.scrollTo(0, 0)
                        return
                    }

                    if (loadedRowCount === null || !firstJob?.getAttribute('href')?.endsWith('/failed-101')) {
                        return
                    }

                    const updatedRow = rows.find(
                        (row) => row.querySelector('a[href$="/failed-100"]') !== null,
                    )

                    finish(null, {
                        loadedRowCount,
                        refreshedRowCount: rows.length,
                        existingRowUpdated: updatedRow?.textContent?.includes('RefreshedImportFeed') ?? false,
                    })
                }

                const toggle = document.querySelector('[aria-label="Auto load new entries"]')

                if (toggle?.getAttribute('aria-pressed') !== 'true') {
                    toggle?.click()
                }

                document.addEventListener('inertia:success', inspect)
                observer.observe(document.querySelector('main'), {
                    childList: true,
                    subtree: true,
                    characterData: true,
                })
                window.scrollTo(0, document.documentElement.scrollHeight)
            })
        JS);

        expect($result)->toBe([
            'loadedRowCount' => 100,
            'refreshedRowCount' => 100,
            'existingRowUpdated' => true,
        ]);

        $page
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });

    it('keeps newer queue query navigation when an older poll response finishes', function (): void {
        bindBrowserPageFixtures();
        config()->set('horizon-new-dawn.poll_interval', 200);

        $page = visit('/horizon/queues/reports');

        $result = $page->script(<<<'JS'
            () => new Promise((resolve, reject) => {
                const originalSend = XMLHttpRequest.prototype.send
                const originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader
                const headers = new WeakMap()
                let heldPoll = null
                let navigationStarted = false
                let metricsNavigationComplete = false
                let scopeNavigationComplete = false
                let followingPollStarted = false
                const timeout = window.setTimeout(() => finish(new Error('Timed out reproducing the poll/navigation overlap.')), 10000)

                function finish(error = null) {
                    window.clearTimeout(timeout)
                    document.removeEventListener('inertia:success', onSuccess)
                    XMLHttpRequest.prototype.send = originalSend
                    XMLHttpRequest.prototype.setRequestHeader = originalSetRequestHeader

                    if (error) {
                        reject(error)
                        return
                    }

                    const metrics = Array.from(document.querySelectorAll('[role="tab"]'))
                        .find((tab) => tab.textContent?.trim() === 'Metrics')
                    const silenced = Array.from(document.querySelectorAll('[role="tab"]'))
                        .find((tab) => tab.textContent?.includes('Silenced Jobs'))
                    const page = window.history.state?.page

                    resolve({
                        url: `${window.location.pathname}${window.location.search}`,
                        metricsSelected: metrics?.getAttribute('aria-selected'),
                        silencedSelected: silenced?.getAttribute('aria-selected'),
                        view: page?.props?.view,
                        tab: page?.props?.tab,
                        activityId: page?.props?.activity?.data?.[0]?.id,
                        hasMetricsHeading: Array.from(document.querySelectorAll('h2'))
                            .some((heading) => heading.textContent?.trim() === 'Throughput — reports'),
                        hasSilencedJob: document.querySelector('a[href*="silenced-1"]') !== null,
                    })
                }

                function onSuccess(event) {
                    if (!navigationStarted || event.detail.page.component !== 'Queues/Show') {
                        return
                    }

                    if (!metricsNavigationComplete && event.detail.page.props.view === 'metrics') {
                        metricsNavigationComplete = true

                        const silenced = Array.from(document.querySelectorAll('[role="tab"]'))
                            .find((tab) => tab.textContent?.includes('Silenced Jobs'))

                        if (!(silenced instanceof HTMLElement)) {
                            finish(new Error('The Silenced Jobs activity tab was not found.'))
                            return
                        }

                        silenced.click()
                        return
                    }

                    if (
                        metricsNavigationComplete &&
                        !scopeNavigationComplete &&
                        event.detail.page.props.tab === 'silenced'
                    ) {
                        scopeNavigationComplete = true
                        heldPoll?.()
                        return
                    }

                    if (scopeNavigationComplete && followingPollStarted) {
                        window.requestAnimationFrame(() => window.requestAnimationFrame(() => finish()))
                    }
                }

                XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
                    const requestHeaders = headers.get(this) ?? {}
                    requestHeaders[name.toLowerCase()] = String(value)
                    headers.set(this, requestHeaders)

                    return originalSetRequestHeader.call(this, name, value)
                }

                XMLHttpRequest.prototype.send = function (body) {
                    const partialData = headers.get(this)?.['x-inertia-partial-data'] ?? ''

                    if (!heldPoll && partialData.includes('summary') && partialData.includes('activity')) {
                        const onload = this.onload

                        this.onload = (event) => {
                            heldPoll = () => onload?.call(this, event)
                            navigationStarted = true

                            const metrics = Array.from(document.querySelectorAll('[role="tab"]'))
                                .find((tab) => tab.textContent?.trim() === 'Metrics')

                            if (!(metrics instanceof HTMLElement)) {
                                finish(new Error('The Metrics queue tab was not found.'))
                                return
                            }

                            document.addEventListener('inertia:success', onSuccess)
                            metrics.click()
                        }
                    } else if (
                        scopeNavigationComplete &&
                        partialData.includes('summary') &&
                        partialData.includes('activity')
                    ) {
                        followingPollStarted = true
                    }

                    return originalSend.call(this, body)
                }

                const toggle = document.querySelector('[aria-label="Auto load new entries"]')

                if (!(toggle instanceof HTMLElement)) {
                    finish(new Error('The auto-load toggle was not found.'))
                    return
                }

                if (toggle.getAttribute('aria-pressed') !== 'true') {
                    toggle.click()
                }
            })
        JS);

        expect($result)->toBe([
            'url' => '/horizon/queues/reports?tab=silenced&view=metrics',
            'metricsSelected' => 'true',
            'silencedSelected' => 'true',
            'view' => 'metrics',
            'tab' => 'silenced',
            'activityId' => 'silenced-1',
            'hasMetricsHeading' => true,
            'hasSilencedJob' => true,
        ]);

        $page
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs();
    });
});
