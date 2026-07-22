<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\ClearableBatches;
use NckRtl\HorizonNewDawn\Http\Requests\BatchIndexRequest;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;
use NckRtl\HorizonNewDawn\Support\Scrolling\HorizonScrollMetadata;

final class BatchController
{
    public function index(
        BatchIndexRequest $request,
        BatchesData $batches,
        ClearableBatches $clearableBatches,
    ): Response {
        $filters = $request->getData();
        $page = $batches->page(
            $request->beforeId(),
            $filters->query,
            $filters->queue,
            $filters->connection,
            $filters->created,
        );

        return Inertia::render('Batches/Index', [
            'meta' => new PageMetaData('Batches', NavigationItem::Batches),
            'query' => $filters->query ?? '',
            'filters' => [
                'queue' => $filters->queue,
                'connection' => $filters->connection,
                'created' => $filters->created?->value,
            ],
            'batchClearCounts' => $clearableBatches->counts(),
            'batches' => Inertia::scroll(
                [
                    'data' => $page->batches,
                    'available' => $page->available,
                    'message' => $page->message,
                ],
                'data',
                new HorizonScrollMetadata('before_id', null, $page->next, $page->current),
            )->matchOn('data.id'),
        ]);
    }

    public function show(BatchesData $batches, string $batch): Response
    {
        $detail = $batches->find($batch);

        abort_if($detail === null, 404);

        return Inertia::render('Batches/Show', [
            'meta' => new PageMetaData($detail->displayName, NavigationItem::Batches),
            'batch' => $detail,
        ]);
    }
}
