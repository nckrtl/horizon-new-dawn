<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Actions;

use Illuminate\Bus\BatchRepository;
use NckRtl\HorizonNewDawn\Batches\BatchesData;

final readonly class RetryQueueBatches
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private BatchRepository $batches,
        private BatchesData $data,
        private RetryBatch $retry,
    ) {}

    public function handle(string $queue): int
    {
        $cursor = null;
        $scheduled = 0;

        while (true) {
            $source = $this->batches->get(self::PAGE_SIZE, $cursor);

            if ($source === []) {
                return $scheduled;
            }

            $lastId = null;

            foreach ($source as $batch) {
                $lastId = $batch->id;

                if ($batch->failedJobs === 0 || $this->data->queue($batch) !== $queue) {
                    continue;
                }

                $scheduled += $this->retry->handle($batch->id);
            }

            if (count($source) < self::PAGE_SIZE || $lastId === $cursor) {
                return $scheduled;
            }

            $cursor = $lastId;
        }
    }
}
