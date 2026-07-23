<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NckRtl\HorizonNewDawn\Http\Controllers\BatchCancelController;
use NckRtl\HorizonNewDawn\Http\Controllers\BatchClearController;
use NckRtl\HorizonNewDawn\Http\Controllers\BatchController;
use NckRtl\HorizonNewDawn\Http\Controllers\BatchFailedJobClearController;
use NckRtl\HorizonNewDawn\Http\Controllers\BatchRetryController;
use NckRtl\HorizonNewDawn\Http\Controllers\DashboardController;
use NckRtl\HorizonNewDawn\Http\Controllers\DelayedJobReleaseController;
use NckRtl\HorizonNewDawn\Http\Controllers\FailedJobClearAllController;
use NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController;
use NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryAllController;
use NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryController;
use NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController;
use NckRtl\HorizonNewDawn\Http\Controllers\HorizonTerminationController;
use NckRtl\HorizonNewDawn\Http\Controllers\JobController;
use NckRtl\HorizonNewDawn\Http\Controllers\MetricController;
use NckRtl\HorizonNewDawn\Http\Controllers\MetricsController;
use NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController;
use NckRtl\HorizonNewDawn\Http\Controllers\MonitoringFailedJobRetryController;
use NckRtl\HorizonNewDawn\Http\Controllers\MonitoringRecentJobController;
use NckRtl\HorizonNewDawn\Http\Controllers\MonitoringTagController;
use NckRtl\HorizonNewDawn\Http\Controllers\PendingJobClearAllController;
use NckRtl\HorizonNewDawn\Http\Controllers\PendingJobController;
use NckRtl\HorizonNewDawn\Http\Controllers\PendingJobsCancellationController;
use NckRtl\HorizonNewDawn\Http\Controllers\QueueBatchRetryController;
use NckRtl\HorizonNewDawn\Http\Controllers\QueueClearAllController;
use NckRtl\HorizonNewDawn\Http\Controllers\QueueClearController;
use NckRtl\HorizonNewDawn\Http\Controllers\QueueController;
use NckRtl\HorizonNewDawn\Http\Controllers\QueueFailedJobRetryController;
use NckRtl\HorizonNewDawn\Http\Controllers\QueuePauseController;
use NckRtl\HorizonNewDawn\Http\Controllers\RunningInstanceController;
use NckRtl\HorizonNewDawn\Http\Controllers\SupervisorController;
use NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController;
use NckRtl\HorizonNewDawn\Http\Middleware\EnsureQueuePausingIsSupported;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/instances', [RunningInstanceController::class, 'index'])->name('instances.index');
Route::post('/supervisors/{supervisor}/pause', [SupervisorPauseController::class, 'store'])
    ->name('supervisors.pause.store');
Route::delete('/supervisors/{supervisor}/pause', [SupervisorPauseController::class, 'destroy'])
    ->name('supervisors.pause.destroy');
Route::get('/supervisors/{supervisor}', [SupervisorController::class, 'show'])->name('supervisors.show');
Route::post('/instances/terminate', [HorizonTerminationController::class, 'store'])
    ->name('instances.terminate.store');
Route::post('/instances/{instance}/pause', [HorizonPauseController::class, 'store'])
    ->name('instances.pause.store');
Route::delete('/instances/{instance}/pause', [HorizonPauseController::class, 'destroy'])
    ->name('instances.pause.destroy');

Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
Route::post('/monitoring', [MonitoringController::class, 'store'])->name('monitoring.store');
Route::delete('/monitoring/actions/clear-jobs/{tag}', [MonitoringRecentJobController::class, 'destroy'])
    ->where('tag', '.+')
    ->name('monitoring.jobs.destroy');
Route::post('/monitoring/actions/retry-failed/{tag}', [MonitoringFailedJobRetryController::class, 'store'])
    ->where('tag', '.+')
    ->name('monitoring.retry-failed.store');
Route::delete('/monitoring/actions/stop/{tag}', [MonitoringController::class, 'destroy'])
    ->where('tag', '.+')
    ->name('monitoring.destroy');
Route::get('/monitoring/{tag}/{status?}', [MonitoringTagController::class, 'show'])
    ->where('tag', '.+?')
    ->where('status', 'jobs|failed')
    ->name('monitoring.show');

Route::get('/metrics', [MetricsController::class, 'redirect'])->name('metrics.redirect');
Route::get('/metrics/{type}', [MetricsController::class, 'index'])
    ->where('type', 'jobs|queues')
    ->name('metrics.index');
Route::get('/metrics/{type}/{slug}', [MetricController::class, 'show'])
    ->where('type', 'jobs|queues')
    ->name('metrics.show');

Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
Route::delete('/batches/{scope}', [BatchClearController::class, 'destroy'])
    ->where('scope', 'incomplete|complete|finished|cancelled')
    ->name('batches.clear.destroy');
Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
Route::post('/batches/{batch}/cancel', [BatchCancelController::class, 'store'])->name('batches.cancel.store');
Route::post('/batches/{batch}/retry', [BatchRetryController::class, 'store'])->name('batches.retry.store');
Route::delete('/batches/{batch}/failed', [BatchFailedJobClearController::class, 'destroy'])
    ->name('batches.failed.clear.destroy');

Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
Route::delete('/queues', [QueueClearAllController::class, 'destroy'])->name('queues.clear-all.destroy');
Route::get('/queues/{queue}', [QueueController::class, 'show'])
    ->where('queue', '.+')
    ->name('queues.show');
Route::post('/queues/{connection}/{queue}/pause', [QueuePauseController::class, 'store'])
    ->middleware(EnsureQueuePausingIsSupported::class)
    ->where('queue', '.+?')
    ->name('queues.pause.store');
Route::delete('/queues/{connection}/{queue}/pause', [QueuePauseController::class, 'destroy'])
    ->middleware(EnsureQueuePausingIsSupported::class)
    ->where('queue', '.+?')
    ->name('queues.pause.destroy');
Route::delete('/queues/{connection}/{queue}/clear', [QueueClearController::class, 'destroy'])
    ->where('queue', '.+?')
    ->name('queues.clear.destroy');
Route::post('/queues/{connection}/{queue}/retry-failed', [QueueFailedJobRetryController::class, 'store'])
    ->where('queue', '.+?')
    ->name('queues.retry-failed.store');
Route::post('/queues/{queue}/batches/retry-failed-jobs', [QueueBatchRetryController::class, 'store'])
    ->where('queue', '.+?')
    ->name('queues.batches.retry-failed.store');

Route::delete('/jobs/pending', [PendingJobClearAllController::class, 'destroy'])->name('jobs.pending.clear.destroy');
Route::delete('/jobs/pending/cancel/{scope}', [PendingJobsCancellationController::class, 'destroy'])
    ->where('scope', 'ready|delayed|pending')
    ->name('jobs.pending.cancel.destroy');
Route::post('/jobs/pending/{job}/release', [DelayedJobReleaseController::class, 'store'])
    ->name('jobs.pending.release.store');
Route::delete('/jobs/pending/{job}', [PendingJobController::class, 'destroy'])->name('jobs.pending.destroy');
Route::get('/jobs/{type}', [JobController::class, 'index'])
    ->where('type', 'pending|completed|silenced')
    ->name('jobs.index');
Route::get('/jobs/{type}/{job}', [JobController::class, 'show'])
    ->where('type', 'pending|completed|silenced')
    ->name('jobs.show');

Route::get('/failed', [FailedJobController::class, 'index'])->name('failed-jobs.index');
Route::delete('/failed', [FailedJobClearAllController::class, 'destroy'])->name('failed-jobs.clear-all.destroy');
Route::post('/failed/retry-all', [FailedJobRetryAllController::class, 'store'])->name('failed-jobs.retry-all.store');
Route::get('/failed/{job}', [FailedJobController::class, 'show'])->name('failed-jobs.show');
Route::delete('/failed/{job}', [FailedJobController::class, 'destroy'])->name('failed-jobs.destroy');
Route::post('/failed/{job}/retry', [FailedJobRetryController::class, 'store'])->name('failed-jobs.retry.store');
