<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringData;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringStatus;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;
use NckRtl\HorizonNewDawn\Support\Scrolling\HorizonScrollMetadata;

final class MonitoringTagController
{
    public function show(
        Request $request,
        MonitoringData $monitoring,
        string $tag,
        ?string $status = null,
    ): Response {
        $monitoringStatus = MonitoringStatus::from($status ?? MonitoringStatus::Jobs->value);
        $summary = $monitoring->summary($tag);
        $page = $monitoring->page($tag, $monitoringStatus, $request->integer('starting_at', 0));
        $title = $monitoringStatus === MonitoringStatus::Failed
            ? "Failed Jobs for \"{$tag}\""
            : "Recent Jobs for \"{$tag}\"";

        return Inertia::render('Monitoring/Show', [
            'meta' => new PageMetaData($title, NavigationItem::Monitoring),
            'tag' => $tag,
            'status' => $monitoringStatus->value,
            'summary' => $summary,
            'jobs' => Inertia::scroll(
                [
                    'data' => $page->items,
                    'total' => $page->total,
                    'available' => $page->available,
                    'message' => $page->message,
                ],
                'data',
                new HorizonScrollMetadata('starting_at', null, $page->next, $page->current),
            )->matchOn('data.id'),
        ]);
    }
}
