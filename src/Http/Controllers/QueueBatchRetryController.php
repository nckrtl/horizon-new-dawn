<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\Batches\Actions\RetryQueueBatches;
use NckRtl\HorizonNewDawn\Http\Requests\RetryFailedJobsRequest;
use Throwable;

final class QueueBatchRetryController
{
    public function store(
        RetryFailedJobsRequest $request,
        RetryQueueBatches $retry,
        string $queue,
    ): RedirectResponse {
        try {
            $count = $retry->handle($queue);
            $message = $count === 1
                ? "Scheduled 1 failed batch job from {$queue} for retry."
                : "Scheduled {$count} failed batch jobs from {$queue} for retry.";

            return back()->with('toast.success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Failed batch jobs from {$queue} could not be retried.");
        }
    }
}
