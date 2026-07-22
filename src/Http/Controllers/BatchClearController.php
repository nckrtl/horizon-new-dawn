<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Batches\Actions\ClearBatches;
use NckRtl\HorizonNewDawn\Batches\BatchClearScope;
use Throwable;

final class BatchClearController
{
    public function destroy(ClearBatches $clear, BatchClearScope $scope): RedirectResponse
    {
        try {
            $count = $clear->handle($scope);

            return back()->with(
                'toast.success',
                "Cleared {$count} {$scope->value} ".Str::plural('batch', $count).'.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'toast.error',
                ucfirst($scope->value).' batches could not be cleared.',
            );
        }
    }
}
