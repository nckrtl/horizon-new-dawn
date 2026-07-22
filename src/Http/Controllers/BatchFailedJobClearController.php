<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\Batches\Actions\ClearBatchFailedJobs;
use Throwable;

final class BatchFailedJobClearController
{
    public function destroy(ClearBatchFailedJobs $clear, string $batch): RedirectResponse
    {
        try {
            $count = $clear->handle($batch);
            $message = $count === 1
                ? 'Cleared 1 failed batch job.'
                : "Cleared {$count} failed batch jobs.";

            return back()->with('toast.success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'Failed batch jobs could not be cleared.');
        }
    }
}
