<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Monitoring\Actions\ClearRecentJobs;
use Throwable;

final class MonitoringRecentJobController
{
    public function destroy(ClearRecentJobs $clear, string $tag): RedirectResponse
    {
        try {
            $count = $clear->handle($tag);

            return back()->with(
                'toast.success',
                "Cleared {$count} recent ".Str::plural('job', $count)." from {$tag}.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Could not clear recent jobs from {$tag}.");
        }
    }
}
