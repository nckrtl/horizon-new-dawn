<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use NckRtl\HorizonNewDawn\Dashboard\DashboardData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardSummaryData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardSupervisorsData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardWorkloadData;
use NckRtl\HorizonNewDawn\Support\Data\PageMetaData;
use NckRtl\HorizonNewDawn\Support\NavigationItem;

final class DashboardController
{
    public function index(DashboardData $dashboard): Response
    {
        return Inertia::render('Dashboard', [
            'meta' => new PageMetaData('Dashboard', NavigationItem::Dashboard),
            'summary' => fn (): DashboardSummaryData => $dashboard->summary(),
            'workload' => fn (): DashboardWorkloadData => $dashboard->workload(),
            'supervisors' => fn (): DashboardSupervisorsData => $dashboard->supervisors(),
        ]);
    }
}
