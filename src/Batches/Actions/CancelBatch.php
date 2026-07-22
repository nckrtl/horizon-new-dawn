<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Actions;

use Illuminate\Bus\BatchRepository;

final readonly class CancelBatch
{
    public function __construct(private BatchRepository $batches) {}

    public function handle(string $id): bool
    {
        $batch = $this->batches->find($id);

        if (
            $batch === null
            || $batch->cancelled()
            || $batch->finished()
            || max(0, $batch->pendingJobs - $batch->failedJobs) === 0
        ) {
            return false;
        }

        $batch->cancel();

        return true;
    }
}
