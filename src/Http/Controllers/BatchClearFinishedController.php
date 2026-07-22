<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Batches\Actions\ClearFinishedBatches;
use Throwable;

final class BatchClearFinishedController
{
    public function destroy(ClearFinishedBatches $clear): RedirectResponse
    {
        try {
            $count = $clear->handle();

            return back()->with(
                'toast.success',
                "Cleared {$count} finished ".Str::plural('batch', $count).'.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'Finished batches could not be cleared.');
        }
    }
}
