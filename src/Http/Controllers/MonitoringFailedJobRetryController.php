<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Monitoring\Actions\RetryFailedJobs;
use Throwable;

final class MonitoringFailedJobRetryController
{
    public function store(RetryFailedJobs $retry, string $tag): RedirectResponse
    {
        try {
            $count = $retry->handle($tag);

            return back()->with(
                'toast.success',
                "Scheduled {$count} failed ".Str::plural('job', $count)." tagged {$tag} for retry.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Failed jobs tagged {$tag} could not be retried.");
        }
    }
}
