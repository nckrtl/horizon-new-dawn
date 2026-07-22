<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard;

use Illuminate\Bus\BatchRepository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchPreviewData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardBatchSummaryData;

final readonly class DashboardBatchSummary
{
    public function __construct(
        private BatchRepository $batches,
        private ConnectionResolverInterface $database,
    ) {}

    public function get(): DashboardBatchSummaryData
    {
        $active = $this->activeQuery()->count();
        $previews = [];

        foreach ($this->activeQuery()->orderByDesc('id')->limit(3)->pluck('id') as $id) {
            if (! is_string($id)) {
                continue;
            }

            $batch = $this->batches->find($id);

            if ($batch === null) {
                continue;
            }

            $name = trim($batch->name);
            $previews[] = new DashboardBatchPreviewData(
                id: $batch->id,
                name: $name === '' ? $batch->id : $name,
                progress: (int) round($batch->progress()),
            );
        }

        return new DashboardBatchSummaryData((int) $active, $previews);
    }

    private function activeQuery(): Builder
    {
        $database = config('queue.batching.database');
        $table = config('queue.batching.table', 'job_batches');

        return $this->database
            ->connection(is_string($database) ? $database : null)
            ->table(is_string($table) ? $table : 'job_batches')
            ->whereNull('cancelled_at')
            ->whereColumn('pending_jobs', '>', 'failed_jobs');
    }
}
