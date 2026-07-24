<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Http\Middleware\Authenticate;
use NckRtl\HorizonNewDawn\Http\Middleware\HandleInertiaRequests;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

describe('New Dawn routes', function (): void {
    it('registers concrete interface routes under the Horizon boundary', function (): void {
        $expected = [
            'horizon-new-dawn.dashboard' => ['GET', 'horizon'],
            'horizon-new-dawn.dashboard.index' => ['GET', 'horizon/dashboard'],
            'horizon-new-dawn.instances.index' => ['GET', 'horizon/instances'],
            'horizon-new-dawn.instances.terminate.store' => ['POST', 'horizon/instances/terminate'],
            'horizon-new-dawn.instances.pause.store' => ['POST', 'horizon/instances/{instance}/pause'],
            'horizon-new-dawn.instances.pause.destroy' => ['DELETE', 'horizon/instances/{instance}/pause'],
            'horizon-new-dawn.supervisors.pause.store' => ['POST', 'horizon/supervisors/{supervisor}/pause'],
            'horizon-new-dawn.supervisors.pause.destroy' => ['DELETE', 'horizon/supervisors/{supervisor}/pause'],
            'horizon-new-dawn.monitoring.index' => ['GET', 'horizon/monitoring'],
            'horizon-new-dawn.monitoring.store' => ['POST', 'horizon/monitoring'],
            'horizon-new-dawn.monitoring.show' => ['GET', 'horizon/monitoring/{tag}/{status?}'],
            'horizon-new-dawn.monitoring.destroy' => ['DELETE', 'horizon/monitoring/actions/stop/{tag}'],
            'horizon-new-dawn.monitoring.jobs.destroy' => ['DELETE', 'horizon/monitoring/actions/clear-jobs/{tag}'],
            'horizon-new-dawn.monitoring.retry-failed.store' => ['POST', 'horizon/monitoring/actions/retry-failed/{tag}'],
            'horizon-new-dawn.metrics.redirect' => ['GET', 'horizon/metrics'],
            'horizon-new-dawn.metrics.index' => ['GET', 'horizon/metrics/{type}'],
            'horizon-new-dawn.metrics.show' => ['GET', 'horizon/metrics/{type}/{slug}'],
            'horizon-new-dawn.batches.index' => ['GET', 'horizon/batches'],
            'horizon-new-dawn.batches.show' => ['GET', 'horizon/batches/{batch}'],
            'horizon-new-dawn.batches.retry.store' => ['POST', 'horizon/batches/{batch}/retry'],
            'horizon-new-dawn.queues.index' => ['GET', 'horizon/queues'],
            'horizon-new-dawn.queues.clear-all.destroy' => ['DELETE', 'horizon/queues'],
            'horizon-new-dawn.queues.show' => ['GET', 'horizon/queues/{queue}'],
            'horizon-new-dawn.queues.pause.store' => ['POST', 'horizon/queues/{connection}/{queue}/pause'],
            'horizon-new-dawn.queues.pause.destroy' => ['DELETE', 'horizon/queues/{connection}/{queue}/pause'],
            'horizon-new-dawn.queues.clear.destroy' => ['DELETE', 'horizon/queues/{connection}/{queue}/clear'],
            'horizon-new-dawn.jobs.index' => ['GET', 'horizon/jobs/{type}'],
            'horizon-new-dawn.jobs.show' => ['GET', 'horizon/jobs/{type}/{job}'],
            'horizon-new-dawn.jobs.pending.destroy' => ['DELETE', 'horizon/jobs/pending/{job}'],
            'horizon-new-dawn.failed-jobs.index' => ['GET', 'horizon/failed'],
            'horizon-new-dawn.failed-jobs.clear-all.destroy' => ['DELETE', 'horizon/failed'],
            'horizon-new-dawn.failed-jobs.retry-all.store' => ['POST', 'horizon/failed/retry-all'],
            'horizon-new-dawn.failed-jobs.show' => ['GET', 'horizon/failed/{job}'],
            'horizon-new-dawn.failed-jobs.destroy' => ['DELETE', 'horizon/failed/{job}'],
            'horizon-new-dawn.failed-jobs.retry.store' => ['POST', 'horizon/failed/{job}/retry'],
        ];

        foreach ($expected as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);

            expect($route)
                ->not->toBeNull()
                ->and($route?->methods())->toContain($method)
                ->and($route?->uri())->toBe($uri)
                ->and($route?->middleware())->toContain('horizon')
                ->and($route?->gatherMiddleware())->toContain(Authenticate::class)
                ->and($route?->gatherMiddleware())->toContain(HandleInertiaRequests::class);
        }
    });

    it('matches concrete routes before the Horizon catch-all', function (): void {
        $dashboard = Route::getRoutes()->match(Request::create('/horizon', 'GET'));
        $metrics = Route::getRoutes()->match(Request::create('/horizon/metrics/jobs', 'GET'));
        $retryAll = Route::getRoutes()->match(Request::create('/horizon/failed/retry-all', 'POST'));

        expect($dashboard->getName())->toBe('horizon-new-dawn.dashboard')
            ->and($metrics->getName())->toBe('horizon-new-dawn.metrics.index')
            ->and($retryAll->getName())->toBe('horizon-new-dawn.failed-jobs.retry-all.store');
    });

    it('exposes queue-wide clearing and individual pending cancellation', function (): void {
        $clearAll = Route::getRoutes()->match(Request::create('/horizon/jobs/pending', 'DELETE'));
        $cancel = Route::getRoutes()->match(Request::create(
            '/horizon/jobs/pending/pending-1',
            'DELETE',
        ));

        expect($clearAll->getName())->toBe('horizon-new-dawn.jobs.pending.clear.destroy')
            ->and($cancel->getName())->toBe('horizon-new-dawn.jobs.pending.destroy');
    });

    it('matches encoded slash-bearing monitored tags without consuming action segments', function (): void {
        $show = Route::getRoutes()->match(Request::create('/horizon/monitoring/customer%2F42/jobs', 'GET'));
        $clear = Route::getRoutes()->match(Request::create('/horizon/monitoring/actions/clear-jobs/customer%2Fjobs', 'DELETE'));
        $retry = Route::getRoutes()->match(Request::create('/horizon/monitoring/actions/retry-failed/customer%2F42', 'POST'));
        $stop = Route::getRoutes()->match(Request::create('/horizon/monitoring/actions/stop/jobs%2Fcustomer', 'DELETE'));

        expect($show->getName())->toBe('horizon-new-dawn.monitoring.show')
            ->and($show->parameter('tag'))->toBe('customer/42')
            ->and($show->parameter('status'))->toBe('jobs')
            ->and($clear->getName())->toBe('horizon-new-dawn.monitoring.jobs.destroy')
            ->and($clear->parameter('tag'))->toBe('customer/jobs')
            ->and($retry->getName())->toBe('horizon-new-dawn.monitoring.retry-failed.store')
            ->and($retry->parameter('tag'))->toBe('customer/42')
            ->and($stop->getName())->toBe('horizon-new-dawn.monitoring.destroy')
            ->and($stop->parameter('tag'))->toBe('jobs/customer')
            ->and(fn () => Route::getRoutes()->match(
                Request::create('/horizon/monitoring/customer%2Fjobs', 'DELETE'),
            ))->toThrow(MethodNotAllowedHttpException::class);
    });

    it('matches encoded slash-bearing queue names without consuming action suffixes', function (): void {
        $show = Route::getRoutes()->match(Request::create('/horizon/queues/reports%2Fdaily', 'GET'));
        $pause = Route::getRoutes()->match(Request::create('/horizon/queues/redis/reports%2Fdaily/pause', 'POST'));
        $resume = Route::getRoutes()->match(Request::create('/horizon/queues/redis/reports%2Fdaily/pause', 'DELETE'));
        $clear = Route::getRoutes()->match(Request::create('/horizon/queues/redis/reports%2Fdaily/clear', 'DELETE'));
        $retry = Route::getRoutes()->match(Request::create('/horizon/queues/redis/reports%2Fdaily/retry-failed', 'POST'));
        $retryBatches = Route::getRoutes()->match(Request::create(
            '/horizon/queues/reports%2Fdaily/batches/retry-failed-jobs',
            'POST',
        ));

        expect($show->getName())->toBe('horizon-new-dawn.queues.show')
            ->and($show->parameter('queue'))->toBe('reports/daily')
            ->and($pause->getName())->toBe('horizon-new-dawn.queues.pause.store')
            ->and($pause->parameter('queue'))->toBe('reports/daily')
            ->and($resume->getName())->toBe('horizon-new-dawn.queues.pause.destroy')
            ->and($resume->parameter('queue'))->toBe('reports/daily')
            ->and($clear->getName())->toBe('horizon-new-dawn.queues.clear.destroy')
            ->and($clear->parameter('queue'))->toBe('reports/daily')
            ->and($retry->getName())->toBe('horizon-new-dawn.queues.retry-failed.store')
            ->and($retry->parameter('queue'))->toBe('reports/daily')
            ->and($retryBatches->getName())->toBe('horizon-new-dawn.queues.batches.retry-failed.store')
            ->and($retryBatches->parameter('queue'))->toBe('reports/daily');
    });

    it('matches encoded slash-bearing metric and supervisor identifiers', function (): void {
        $metric = Route::getRoutes()->match(Request::create('/horizon/metrics/jobs/App%5CJobs%5CImport%2FOrders', 'GET'));
        $supervisorShow = Route::getRoutes()->match(Request::create('/horizon/supervisors/local-host-a1b2%3Aimports%2Fworker', 'GET'));
        $supervisorPause = Route::getRoutes()->match(Request::create('/horizon/supervisors/local-host-a1b2%3Aimports%2Fworker/pause', 'POST'));
        $supervisorContinue = Route::getRoutes()->match(Request::create('/horizon/supervisors/local-host-a1b2%3Aimports%2Fworker/pause', 'DELETE'));

        expect($metric->getName())->toBe('horizon-new-dawn.metrics.show')
            ->and($metric->parameter('slug'))->toBe('App\\Jobs\\Import/Orders')
            ->and($supervisorShow->getName())->toBe('horizon-new-dawn.supervisors.show')
            ->and($supervisorShow->parameter('supervisor'))->toBe('local-host-a1b2:imports/worker')
            ->and($supervisorPause->getName())->toBe('horizon-new-dawn.supervisors.pause.store')
            ->and($supervisorPause->parameter('supervisor'))->toBe('local-host-a1b2:imports/worker')
            ->and($supervisorContinue->getName())->toBe('horizon-new-dawn.supervisors.pause.destroy')
            ->and($supervisorContinue->parameter('supervisor'))->toBe('local-host-a1b2:imports/worker');
    });

    it('constrains route-backed interface states', function (): void {
        $metrics = Route::getRoutes()->getByName('horizon-new-dawn.metrics.index');
        $metricShow = Route::getRoutes()->getByName('horizon-new-dawn.metrics.show');
        $jobs = Route::getRoutes()->getByName('horizon-new-dawn.jobs.index');
        $monitoring = Route::getRoutes()->getByName('horizon-new-dawn.monitoring.show');
        $monitoringClear = Route::getRoutes()->getByName('horizon-new-dawn.monitoring.jobs.destroy');
        $monitoringRetry = Route::getRoutes()->getByName('horizon-new-dawn.monitoring.retry-failed.store');
        $monitoringStop = Route::getRoutes()->getByName('horizon-new-dawn.monitoring.destroy');
        $supervisorShow = Route::getRoutes()->getByName('horizon-new-dawn.supervisors.show');
        $supervisorPause = Route::getRoutes()->getByName('horizon-new-dawn.supervisors.pause.store');
        $supervisorContinue = Route::getRoutes()->getByName('horizon-new-dawn.supervisors.pause.destroy');

        expect($metrics?->wheres['type'] ?? null)->toBe('jobs|queues')
            ->and($metricShow?->wheres['slug'] ?? null)->toBe('.+')
            ->and($jobs?->wheres['type'] ?? null)->toBe('pending|completed|silenced')
            ->and($monitoring?->wheres['tag'] ?? null)->toBe('.+?')
            ->and($monitoring?->wheres['status'] ?? null)->toBe('jobs|failed')
            ->and($monitoringClear?->wheres['tag'] ?? null)->toBe('.+')
            ->and($monitoringRetry?->wheres['tag'] ?? null)->toBe('.+')
            ->and($monitoringStop?->wheres['tag'] ?? null)->toBe('.+')
            ->and($supervisorShow?->wheres['supervisor'] ?? null)->toBe('.+')
            ->and($supervisorPause?->wheres['supervisor'] ?? null)->toBe('.+?')
            ->and($supervisorContinue?->wheres['supervisor'] ?? null)->toBe('.+?');
    });
});
