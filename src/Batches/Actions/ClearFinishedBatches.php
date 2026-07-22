<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PrunableBatchRepository;
use RuntimeException;

final readonly class ClearFinishedBatches
{
    public function __construct(private BatchRepository $batches) {}

    public function handle(): int
    {
        if (! $this->batches instanceof PrunableBatchRepository) {
            throw new RuntimeException('The configured batch repository cannot prune batches.');
        }

        return $this->batches->prune(CarbonImmutable::now()->addSecond());
    }
}
