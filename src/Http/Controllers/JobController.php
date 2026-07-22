<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Jobs\JobListType;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\Scrolling\HorizonScrollMetadata;

final class JobController
{
    public function index(Request $request, JobsData $jobs, string $type): Response
    {
        $jobType = JobListType::from($type);
        $page = $jobs->page($jobType, $request->integer('starting_at', -1));

        return Inertia::render('Jobs/Index', [
            'meta' => new PageMetaData($jobType->title(), $jobType->navigation()),
            'type' => $jobType->value,
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

    public function show(JobsData $jobs, string $type, string $job): Response
    {
        $jobType = JobListType::from($type);
        $detail = $jobs->find($job);

        abort_if($detail === null, 404);

        return Inertia::render('Jobs/Show', [
            'meta' => new PageMetaData('Job Detail', $jobType->navigation()),
            'type' => $jobType->value,
            'job' => $detail,
        ]);
    }
}
