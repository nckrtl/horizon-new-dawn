<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\FailedJobs\Actions\RemoveFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;
use NckRtl\HorizonNewDawn\Support\Scrolling\HorizonScrollMetadata;
use Throwable;

final class FailedJobController
{
    public function index(Request $request, FailedJobsData $jobs): Response
    {
        $query = trim($request->string('tag')->toString());
        $page = $jobs->page(
            $request->integer('starting_at', $query === '' ? -1 : 0),
            $query === '' ? null : $query,
        );

        return Inertia::render('FailedJobs/Index', [
            'meta' => new PageMetaData('Failed Jobs', NavigationItem::Failed),
            'query' => $query,
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

    public function show(FailedJobsData $jobs, string $job): Response
    {
        $detail = $jobs->find($job);

        abort_if($detail === null, 404);

        return Inertia::render('FailedJobs/Show', [
            'meta' => new PageMetaData('Failed Job Detail', NavigationItem::Failed),
            'job' => $detail,
        ]);
    }

    public function destroy(RemoveFailedJob $remove, string $job): RedirectResponse
    {
        try {
            $remove->handle($job);

            return to_route('horizon-new-dawn.failed-jobs.index')
                ->with('toast.success', "Removed failed job {$job}.");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast.error', 'The failed job could not be removed.');
        }
    }
}
