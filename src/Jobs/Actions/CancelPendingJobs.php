<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Actions;

use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Jobs\Data\CancelPendingJobsResultData;
use NckRtl\HorizonNewDawn\Jobs\PendingJobCancellationResult;
use NckRtl\HorizonNewDawn\Jobs\PendingJobCancellationScope;
use Throwable;

final readonly class CancelPendingJobs
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private JobRepository $jobs,
        private CancelPendingJob $cancel,
    ) {}

    public function handle(
        PendingJobCancellationScope $scope,
        ?string $queue = null,
    ): CancelPendingJobsResultData {
        $cancelled = 0;
        $batched = 0;
        $failed = 0;

        foreach ($this->pendingJobIds($queue) as $id) {
            try {
                match ($this->cancel->handle($id, $scope)) {
                    PendingJobCancellationResult::Cancelled => $cancelled++,
                    PendingJobCancellationResult::Batched => $batched++,
                    PendingJobCancellationResult::NotCancellable => null,
                };
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return new CancelPendingJobsResultData($cancelled, $batched, $failed);
    }

    /** @return array<int, string> */
    private function pendingJobIds(?string $queue): array
    {
        $total = max(0, (int) $this->jobs->countPending());
        $ids = [];

        for ($inspected = 0; $inspected < $total; $inspected += self::PAGE_SIZE) {
            foreach ($this->jobs->getPending((string) ($inspected - 1)) as $job) {
                $id = is_object($job) ? ($job->id ?? null) : null;
                $jobQueue = is_object($job) ? ($job->queue ?? null) : null;

                if (
                    is_string($id)
                    && $id !== ''
                    && ($queue === null || $jobQueue === $queue)
                ) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }
}
