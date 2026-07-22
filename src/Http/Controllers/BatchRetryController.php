<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\Batches\Actions\RetryBatch;
use NckRtl\HorizonNewDawn\Http\Requests\RetryFailedJobsRequest;
use Throwable;

final class BatchRetryController
{
    public function store(
        RetryFailedJobsRequest $request,
        RetryBatch $retry,
        string $batch,
    ): RedirectResponse {
        try {
            $count = $retry->handle($batch);
            $message = $count === 1
                ? 'Scheduled 1 failed batch job for retry.'
                : "Scheduled {$count} failed batch jobs for retry.";

            return back()->with('toast.success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'Failed batch jobs could not be retried.');
        }
    }
}
