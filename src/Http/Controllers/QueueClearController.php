<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Http\Requests\ClearQueueRequest;
use NckRtl\HorizonNewDawn\Queues\Actions\ClearQueue;
use Throwable;

final class QueueClearController
{
    public function destroy(ClearQueueRequest $request, ClearQueue $clear): RedirectResponse
    {
        $data = $request->getData();

        try {
            $count = $clear->handle($data);

            return back()->with(
                'toast.success',
                "Cleared {$count} ".Str::plural('job', $count)." from {$data->queue}.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Could not clear {$data->queue}.");
        }
    }
}
