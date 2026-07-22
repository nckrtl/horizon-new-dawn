<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use NckRtl\HorizonNewDawn\Http\Requests\PauseQueueRequest;
use NckRtl\HorizonNewDawn\Http\Requests\ResumeQueueRequest;
use NckRtl\HorizonNewDawn\Queues\Actions\PauseQueue;
use NckRtl\HorizonNewDawn\Queues\Actions\ResumeQueue;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use Throwable;

final class QueuePauseController
{
    public function __construct(
        private readonly FrameworkCapabilities $capabilities,
    ) {}

    public function store(PauseQueueRequest $request, PauseQueue $pause): RedirectResponse
    {
        abort_unless($this->capabilities->queuePausing, 404);

        $data = $request->getData();

        try {
            $pause->handle($data);

            return back()->with(
                'toast.success',
                "Paused {$data->queue} {$this->durationDescription($data->durationMinutes)}.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Could not pause {$data->queue}.");
        }
    }

    public function destroy(ResumeQueueRequest $request, ResumeQueue $resume): RedirectResponse
    {
        abort_unless($this->capabilities->queuePausing, 404);

        $data = $request->getData();

        try {
            $resume->handle($data->connection, $data->queue);

            return back()->with('toast.success', "Resumed {$data->queue}.");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Could not resume {$data->queue}.");
        }
    }

    private function durationDescription(?int $durationMinutes): string
    {
        if ($durationMinutes === null) {
            return 'indefinitely';
        }

        if ($durationMinutes % 60 === 0) {
            $hours = intdiv($durationMinutes, 60);

            return "for {$hours} ".($hours === 1 ? 'hour' : 'hours');
        }

        return "for {$durationMinutes} ".($durationMinutes === 1 ? 'minute' : 'minutes');
    }
}
