<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Actions;

use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Jobs\Data\ClearPendingJobsResultData;
use NckRtl\HorizonNewDawn\Queues\Actions\ClearQueue;
use NckRtl\HorizonNewDawn\Queues\Data\QueueTargetData;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use Throwable;

final readonly class ClearPendingJobs
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private QueuesData $queues,
        private JobRepository $jobs,
        private ClearQueue $clearQueue,
    ) {}

    public function handle(): ClearPendingJobsResultData
    {
        $cleared = 0;
        $failedTargets = [];

        foreach ($this->targets() as $target) {
            try {
                $cleared += $this->clearQueue->handle($target);
            } catch (Throwable $exception) {
                report($exception);
                $failedTargets[] = "{$target->connection}:{$target->queue}";
            }
        }

        return new ClearPendingJobsResultData($cleared, $failedTargets);
    }

    /** @return array<int, QueueTargetData> */
    private function targets(): array
    {
        $targets = [];

        foreach ($this->queues->targets() as $target) {
            $targets[$this->targetKey($target)] = $target;
        }

        try {
            $sourceTotal = max(0, (int) $this->jobs->countPending());

            for ($inspected = 0; $inspected < $sourceTotal; $inspected += self::PAGE_SIZE) {
                $pendingJobs = $this->jobs->getPending((string) ($inspected - 1));

                foreach ($pendingJobs as $job) {
                    if (! is_object($job) || ! in_array($job->status ?? null, ['pending', 'reserved'], true)) {
                        continue;
                    }

                    $connection = $job->connection ?? null;
                    $queue = $job->queue ?? null;

                    if (! is_string($connection) || $connection === '' || ! is_string($queue) || $queue === '') {
                        continue;
                    }

                    $target = new QueueTargetData($connection, $queue);
                    $targets[$this->targetKey($target)] = $target;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return array_values($targets);
    }

    private function targetKey(QueueTargetData $target): string
    {
        return $target->connection."\0".$target->queue;
    }
}
