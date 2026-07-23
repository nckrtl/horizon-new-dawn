<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\Jobs\Actions\ReleaseDelayedJobNow;
use NckRtl\HorizonNewDawn\Jobs\ReleaseDelayedJobNowResult;
use Throwable;

final class DelayedJobReleaseController
{
    public function store(ReleaseDelayedJobNow $release, string $job): RedirectResponse
    {
        try {
            return match ($release->handle($job)) {
                ReleaseDelayedJobNowResult::Released => back()->with(
                    'toast.success',
                    'Job is now available to workers.',
                ),
                ReleaseDelayedJobNowResult::NotDelayed => back()->with(
                    'toast.error',
                    'This job is no longer delayed.',
                ),
            };
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'The job could not be made available.');
        }
    }
}
