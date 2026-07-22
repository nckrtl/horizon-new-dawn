<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryAllFailedJobs;
use NckRtl\HorizonNewDawn\Http\Requests\RetryFailedJobsRequest;
use Throwable;

final class FailedJobRetryAllController
{
    public function store(
        RetryFailedJobsRequest $request,
        RetryAllFailedJobs $retry,
    ): RedirectResponse {
        try {
            $count = $retry->handle();
            $message = $count === 1
                ? 'Scheduled 1 failed job for retry.'
                : "Scheduled {$count} failed jobs for retry.";

            return back()->with('toast.success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'Failed jobs could not be retried.');
        }
    }
}
