<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\ClearFailedJobs;
use Throwable;

final class FailedJobClearAllController
{
    public function destroy(ClearFailedJobs $clear): RedirectResponse
    {
        try {
            $count = $clear->handle();
            $message = $count === 1
                ? 'Cleared 1 failed job.'
                : "Cleared {$count} failed jobs.";

            return back()->with('toast.success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'Failed jobs could not be cleared.');
        }
    }
}
