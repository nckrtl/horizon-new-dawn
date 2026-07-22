<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use NckRtl\HorizonNewDawn\Queues\Data\QueueActivityPageData;

final readonly class QueueActivityData
{
    public function __construct(
        private QueueJobsData $jobs,
        private QueueBatchesData $batches,
    ) {}

    public function page(
        string $queue,
        QueueActivityTab $tab,
        int|string|null $cursor,
    ): QueueActivityPageData {
        return match ($tab) {
            QueueActivityTab::Pending,
            QueueActivityTab::Completed,
            QueueActivityTab::Failed,
            QueueActivityTab::Silenced => $this->jobs->page(
                $queue,
                $tab,
                is_numeric($cursor) ? (int) $cursor : -1,
            ),
            QueueActivityTab::Batches => $this->batches->page(
                $queue,
                is_string($cursor) ? $cursor : null,
            ),
        };
    }
}
