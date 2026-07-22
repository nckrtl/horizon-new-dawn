<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Actions;

use Illuminate\Bus\BatchRepository;
use NckRtl\HorizonNewDawn\Batches\BatchClearScope;
use NckRtl\HorizonNewDawn\Batches\ClearableBatches;

final readonly class ClearBatches
{
    public function __construct(
        private BatchRepository $batches,
        private ClearableBatches $clearable,
    ) {}

    public function handle(BatchClearScope $scope): int
    {
        $ids = $this->clearable->ids($scope);

        return $this->batches->transaction(function () use ($ids): int {
            foreach ($ids as $id) {
                $this->batches->delete($id);
            }

            return count($ids);
        });
    }
}
