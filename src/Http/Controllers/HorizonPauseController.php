<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use NckRtl\HorizonNewDawn\Instances\Actions\ContinueHorizon;
use NckRtl\HorizonNewDawn\Instances\Actions\PauseHorizon;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class HorizonPauseController
{
    public function store(
        Request $request,
        string $instance,
        PauseHorizon $pause,
    ): RedirectResponse|JsonResponse {
        return $this->run(
            request: $request,
            command: function () use ($pause, $instance): void {
                $pause->handle($instance);
            },
            success: 'Horizon pause requested.',
            failure: 'Horizon could not be paused.',
        );
    }

    public function destroy(
        Request $request,
        string $instance,
        ContinueHorizon $continue,
    ): RedirectResponse|JsonResponse {
        return $this->run(
            request: $request,
            command: function () use ($continue, $instance): void {
                $continue->handle($instance);
            },
            success: 'Horizon continue requested.',
            failure: 'Horizon could not be continued.',
        );
    }

    /** @param Closure(): void $command */
    private function run(
        Request $request,
        Closure $command,
        string $success,
        string $failure,
    ): RedirectResponse|JsonResponse {
        try {
            $command();

            if ($request->expectsJson()) {
                return response()->json(['message' => $success], Response::HTTP_ACCEPTED);
            }

            return back()->with('toast.success', $success);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $failure], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return back()->with('toast.error', $failure);
        }
    }
}
