<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Jobs\Actions\ClearPendingJobs;

final class PendingJobClearAllController
{
    public function destroy(ClearPendingJobs $clear): RedirectResponse
    {
        $result = $clear->handle();
        $jobs = Str::plural('job', $result->cleared);

        if ($result->failedTargets !== []) {
            return back()->with(
                'toast.error',
                "Cleared {$result->cleared} pending {$jobs}, but could not clear ".implode(', ', $result->failedTargets).'.',
            );
        }

        return back()->with('toast.success', "Cleared {$result->cleared} pending {$jobs}.");
    }
}
