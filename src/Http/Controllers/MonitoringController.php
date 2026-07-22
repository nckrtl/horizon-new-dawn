<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Http\Requests\MonitorTagRequest;
use NckRtl\HorizonNewDawn\Monitoring\Actions\MonitorTag;
use NckRtl\HorizonNewDawn\Monitoring\Actions\StopMonitoringTag;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringData;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;
use Throwable;

final class MonitoringController
{
    public function index(MonitoringData $monitoring): Response
    {
        $page = $monitoring->index();

        return Inertia::render('Monitoring/Index', [
            'meta' => new PageMetaData('Monitoring', NavigationItem::Monitoring),
            'tags' => [
                'data' => $page->tags,
                'available' => $page->available,
                'message' => $page->message,
            ],
        ]);
    }

    public function store(MonitorTagRequest $request, MonitorTag $monitor): RedirectResponse
    {
        $tag = $request->string('tag')->toString();

        try {
            $monitor->handle($tag);

            return back()->with('toast.success', "Now monitoring {$tag}.");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Could not monitor {$tag}.");
        }
    }

    public function destroy(StopMonitoringTag $stop, string $tag): RedirectResponse
    {
        try {
            $stop->handle($tag);

            return back()->with('toast.success', "Stopped monitoring {$tag}.");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', "Could not stop monitoring {$tag}.");
        }
    }
}
