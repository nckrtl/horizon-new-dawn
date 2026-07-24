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

            $nextCursor = $this->advanceCursor($source, $cursor);

            if ($nextCursor === null) {
                return $scheduled;
            }

            foreach ($source as $batch) {
                if ($batch->failedJobs === 0 || $this->data->queue($batch) !== $queue) {
                    continue;
                }

                $scheduled += $this->retry->handle($batch->id);
            }

            $cursor = $nextCursor;
        }
    }

    /**
     * @param  array<int, object>  $batches
     */
    private function advanceCursor(array $batches, ?string $current): ?string
    {
        $last = end($batches);
        $cursor = is_object($last) && is_string($last->id ?? null) ? $last->id : null;

        if ($cursor === null || $cursor === '' || ($current !== null && strcmp($cursor, $current) >= 0)) {
            return null;
        }

        return $cursor;
    }
}
