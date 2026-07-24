<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard;

use NckRtl\HorizonNewDawn\Batches\BatchRepositoryOverview;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchPreviewData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchSummaryData;

final readonly class DashboardBatchSummary
{
    public function __construct(private BatchRepositoryOverview $batches) {}

    public function get(): DashboardBatchSummaryData
    {
        $overview = $this->batches->get();

        return new DashboardBatchSummaryData(
            $overview['active'],
            array_map(
                static fn (array $preview): DashboardBatchPreviewData => new DashboardBatchPreviewData(
                    id: $preview['id'],
                    name: $preview['name'],
                    progress: $preview['progress'],
                ),
                $overview['previews'],
            ),
        );
    }
}
