<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Dashboard\DashboardData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardSupervisorsData;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;

final class RunningInstanceController
{
    public function index(DashboardData $dashboard): Response
    {
        return Inertia::render('Instances/Index', [
            'meta' => new PageMetaData('Instances', NavigationItem::Instances),
            'supervisors' => fn (): DashboardSupervisorsData => $dashboard->supervisors(),
        ]);
    }
}
