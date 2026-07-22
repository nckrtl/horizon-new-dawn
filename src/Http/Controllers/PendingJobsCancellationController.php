<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Jobs\Actions\CancelPendingJobs;
use NckRtl\HorizonNewDawn\Jobs\PendingJobCancellationScope;
use Throwable;

final class PendingJobsCancellationController
{
    public function destroy(
        CancelPendingJobs $cancel,
        PendingJobCancellationScope $scope,
        Request $request,
    ): RedirectResponse {
        $queue = $request->query('queue');
        $queue = is_string($queue) && $queue !== '' ? $queue : null;

        try {
            $result = $cancel->handle($scope, $queue);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'The pending jobs could not be cancelled.');
        }

        $label = $scope === PendingJobCancellationScope::Pending
            ? 'pending'
            : $scope->value;
        $message = 'Cancelled '.$result->cancelled.' '.$label.' '.Str::plural('job', $result->cancelled);

        if ($queue !== null) {
            $message .= ' from '.$queue;
        }

        $message .= '.';

        if ($result->batched > 0) {
            $message .= ' Skipped '.$result->batched.' batched '.Str::plural('job', $result->batched).'; cancel their '.Str::plural('batch', $result->batched).' instead.';
        }

        if ($result->failed > 0) {
            return back()->with(
                'toast.error',
                $message.' '.$result->failed.' '.Str::plural('job', $result->failed).' could not be cancelled.',
            );
        }

        return back()->with('toast.success', $message);
    }
}
