<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support;

use Closure;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use NckRtl\HorizonNewDawn\Batches\BatchRepositoryOverview;
use NckRtl\HorizonNewDawn\Queues\QueuesData;
use NckRtl\HorizonNewDawn\Support\Data\NavigationCountsData;
use Throwable;

final readonly class NavigationCounts
{
    public function __construct(
        private RedisFactory $redis,
        private JobRepository $jobs,
        private BatchRepositoryOverview $batches,
        private QueuesData $queues,
        private MasterSupervisorRepository $masters,
    ) {}

    public function get(): NavigationCountsData
    {
        return new NavigationCountsData(
            instances: $this->safely(fn (): int => count($this->masters->all())),
            monitoring: $this->safely(fn (): int => $this->setCount('monitoring')),
            metrics: $this->safely(fn (): int => $this->setCount('measured_jobs') + $this->setCount('measured_queues')),
            queues: $this->safely($this->queues->count(...)),
            batches: $this->safely($this->batchCount(...)),
            pending: $this->safely(fn (): int => $this->jobs->countPending()),
            completed: $this->safely(fn (): int => $this->jobs->countCompleted()),
            silenced: $this->safely(fn (): int => $this->jobs->countSilenced()),
            failed: $this->safely(fn (): int => $this->jobs->countFailed()),
        );
    }

    private function setCount(string $key): int
    {
        return (int) $this->redis->connection('horizon')->scard($key);
    }

    private function batchCount(): int
    {
        return $this->batches->get()['total'];
    }

    private function safely(Closure $count): ?int
    {
        try {
            return (int) $count();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
