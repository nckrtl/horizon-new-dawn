<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryFailedJob;
use NckRtl\HorizonNewDawn\Http\Requests\RetryFailedJobsRequest;
use Throwable;

final class FailedJobRetryController
{
    public function store(
        RetryFailedJobsRequest $request,
        RetryFailedJob $retry,
        string $job,
    ): RedirectResponse {
        try {
            if (! $retry->handle($job)) {
                return back()->with(
                    'toast.error',
                    "No retry was scheduled because {$job} is no longer eligible.",
                );
            }

            return back()->with('toast.success', "Retry scheduled for {$job}.");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'The failed job could not be retried.');
        }
    }
}
