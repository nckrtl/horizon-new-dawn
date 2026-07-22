<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RetryAllFailedJobs;
use NckRtl\HorizonNewDawn\Http\Requests\RetryQueueFailedJobsRequest;
use Throwable;

final class QueueFailedJobRetryController
{
    public function store(
        RetryQueueFailedJobsRequest $request,
        RetryAllFailedJobs $retry,
    ): RedirectResponse {
        $data = $request->getData();

        try {
            $count = $retry->handle($data->connection, $data->queue);

            return back()->with(
                'toast.success',
                "Scheduled {$count} failed ".Str::plural('job', $count)." from {$data->queue} for retry.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Failed jobs from {$data->queue} could not be retried.");
        }
    }
}
