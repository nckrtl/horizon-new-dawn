<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Metrics\Data\MetricPreviewData;
use NckRtl\HorizonNewDawn\Metrics\MetricsData;
use NckRtl\HorizonNewDawn\Metrics\MetricType;
use NckRtl\HorizonNewDawn\Queues\Data\QueueActivityPageData;
use NckRtl\HorizonNewDawn\Queues\QueueActivityData;
use NckRtl\HorizonNewDawn\Queues\QueueActivityTab;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Queues\QueueSummary;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;
use NckRtl\HorizonNewDawn\Support\Scrolling\HorizonScrollMetadata;

final class QueueController
{
    public function index(QueuesData $queues): Response
    {
        return Inertia::render('Queues/Index', [
            'meta' => new PageMetaData('Queues', NavigationItem::Queues),
            'queues' => $queues->all(),
        ]);
    }

    public function show(
        Request $request,
        QueuesData $queues,
        QueueSummary $summary,
        QueueActivityData $activity,
        MetricsData $metrics,
        string $queue,
    ): Response {
        $catalog = $queues->all();
        $row = $catalog->find($queue);
        $view = $request->string('view')->toString() === 'metrics' ? 'metrics' : 'overview';
        $tab = QueueActivityTab::tryFrom($request->string('tab')->toString())
            ?? QueueActivityTab::Pending;

        if ($row === null) {
            abort_if($catalog->available && ! $this->isPartialReload($request), 404);

            $message = $catalog->message ?? 'This queue is no longer supervised by Horizon.';

            return $this->detailResponse(
                queue: $queue,
                view: $view,
                tab: $tab,
                summary: QueueSummary::unavailable($queue, $message),
                activity: QueueActivityPageData::unavailable($this->pageName($tab), $message),
                preview: null,
            );
        }

        $cursor = $tab === QueueActivityTab::Batches
            ? $request->input('before_id')
            : $request->integer('starting_at', -1);

        return $this->detailResponse(
            queue: $queue,
            view: $view,
            tab: $tab,
            summary: $summary->forQueue($row),
            activity: $activity->page($queue, $tab, $cursor),
            preview: $view === 'metrics'
                ? $metrics->preview(MetricType::Queues, $queue)
                : null,
        );
    }

    private function detailResponse(
        string $queue,
        string $view,
        QueueActivityTab $tab,
        mixed $summary,
        QueueActivityPageData $activity,
        ?MetricPreviewData $preview,
    ): Response {
        return Inertia::render('Queues/Show', [
            'meta' => new PageMetaData($queue, NavigationItem::Queues),
            'queue' => $queue,
            'view' => $view,
            'summary' => $summary,
            'tab' => $tab->value,
            'preview' => $preview === null ? null : [
                'data' => $preview->snapshots,
                'available' => $preview->available,
                'message' => $preview->message,
            ],
            'activity' => Inertia::scroll(
                [
                    'data' => $activity->rows,
                    'total' => $activity->total,
                    'complete' => $activity->complete,
                    'available' => $activity->available,
                    'message' => $activity->message,
                ],
                'data',
                new HorizonScrollMetadata(
                    $activity->pageName,
                    null,
                    $activity->next,
                    $activity->current,
                ),
            )->matchOn('data.id'),
        ]);
    }

    private function isPartialReload(Request $request): bool
    {
        return $request->header('X-Inertia-Partial-Component') === 'Queues/Show';
    }

    private function pageName(QueueActivityTab $tab): string
    {
        return $tab === QueueActivityTab::Batches ? 'before_id' : 'starting_at';
    }
}
