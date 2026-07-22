<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Supervisors\SupervisorDetails;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;

final class SupervisorController
{
    public function show(SupervisorDetails $supervisors, string $supervisor): Response
    {
        $details = $supervisors->find($supervisor);

        abort_if($details->available && $details->supervisor === null, 404);
        $title = $details->supervisor === null ? 'Supervisor' : $details->supervisor->name;

        return Inertia::render('Supervisors/Show', [
            'meta' => new PageMetaData(
                $title,
                NavigationItem::Instances,
            ),
            'supervisorDetails' => $details,
        ]);
    }
}
