<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use NckRtl\HorizonNewDawn\Instances\Actions\TerminateHorizon;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class HorizonTerminationController
{
    public function store(Request $request, TerminateHorizon $terminate): RedirectResponse|JsonResponse
    {
        try {
            $terminate->handle();

            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => 'Horizon termination requested.'],
                    Response::HTTP_ACCEPTED,
                );
            }

            return back()->with('toast.success', 'Horizon termination requested.');
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => 'Horizon could not be terminated.'],
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
            }

            return back()->with('toast.error', 'Horizon could not be terminated.');
        }
    }
}
