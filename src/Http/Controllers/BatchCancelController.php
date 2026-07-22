<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\Batches\Actions\CancelBatch;
use Throwable;

final class BatchCancelController
{
    public function store(CancelBatch $cancel, string $batch): RedirectResponse
    {
        try {
            if (! $cancel->handle($batch)) {
                return back()->with('toast.error', 'This batch can no longer be cancelled.');
            }

            return back()->with('toast.success', 'Batch cancelled.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'The batch could not be cancelled.');
        }
    }
}
