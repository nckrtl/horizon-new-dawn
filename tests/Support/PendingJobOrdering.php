<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests\Support;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

final class PendingJobOrderingHydrationSpy
{
    /** @var array<int, string> */
    public array $ids = [];
}

final class PendingJobOrderingRedisConnection extends Connection
{
    /**
     * @param  array<string, int|float>  $pendingScores
     * @param  array<string, array<string, int|float>>  $sortedSets
     * @param  array<string, array<int, string>>  $lists
     */
    public function __construct(
        private readonly array $pendingScores,
        private readonly array $sortedSets = [],
        private readonly array $lists = [],
    ) {}

    /** @param array<int, string>|string $channels */
    public function createSubscription($channels, $callback, $method = 'subscribe'): void {}

    /** @param array<int, mixed> $parameters */
    public function command($method, array $parameters = []): mixed
    {
        return match (mb_strtolower($method)) {
            'zrevrange' => $this->pendingRange($parameters),
            'zrange' => $this->sortedSetRange($parameters),
            'lrange' => $this->listRange($parameters),
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return array<int, string>|array<string, int|float>
     */
    private function pendingRange(array $parameters): array
    {
        if (count($parameters) >= 4) {
            return $this->pendingScores;
        }

        $start = is_numeric($parameters[1] ?? null) ? (int) $parameters[1] : 0;
        $stop = is_numeric($parameters[2] ?? null) ? (int) $parameters[2] : -1;
        $length = $stop < 0 ? null : max(0, $stop - $start + 1);

        return array_slice(array_keys($this->pendingScores), $start, $length);
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return array<int, string>|array<string, int|float>
     */
    private function sortedSetRange(array $parameters): array
    {
        $key = is_string($parameters[0] ?? null) ? $parameters[0] : '';
        $entries = $this->sortedSets[$key] ?? [];

        return count($parameters) >= 4 ? $entries : array_keys($entries);
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return array<int, string>
     */
    private function listRange(array $parameters): array
    {
        $key = is_string($parameters[0] ?? null) ? $parameters[0] : '';

        return $this->lists[$key] ?? [];
    }
}

final class PendingJobOrderingRedisQueue extends RedisQueue
{
    public function __construct(private readonly Connection $redisConnection) {}

    public function getConnection(): Connection
    {
        return $this->redisConnection;
    }

    public function getQueue($queue): string
    {
        return 'queues:'.($queue ?: 'default');
    }
}

/**
 * @param  array<string, HorizonJob>  $jobsById
 * @param  array<string, int|float>  $pendingScores
 * @param  array<string, array<string, int|float>>  $delayedScores
 * @param  array<string, int>  $processes
 * @param  array<string, array<int, string>>  $readyPayloads
 * @param  array<string, array<string, int|float>>  $reservedPayloads
 */
function bindPendingJobOrdering(
    array $jobsById,
    array $pendingScores,
    array $delayedScores,
    array $processes = ['redis:default' => 1],
    array $readyPayloads = [],
    array $reservedPayloads = [],
): PendingJobOrderingHydrationSpy {
    $hydration = new PendingJobOrderingHydrationSpy;
    $repository = mockDashboardContract(JobRepository::class);
    dashboardReturnsUsing(
        $repository,
        'getJobs',
        static function (array $ids, int $startingAt = 0) use ($hydration, $jobsById): Collection {
            $hydration->ids = array_values($ids);
            $jobs = [];

            foreach ($ids as $offset => $id) {
                if (! isset($jobsById[$id])) {
                    continue;
                }

                $job = clone $jobsById[$id];
                $job->index = $startingAt + $offset;
                $jobs[] = $job;
            }

            return new Collection($jobs);
        },
    );
    dashboardReturns($repository, 'countPending', count($pendingScores));
    app()->instance(JobRepository::class, $repository);

    $horizonConnection = new PendingJobOrderingRedisConnection($pendingScores);
    $queueConnection = new PendingJobOrderingRedisConnection(
        [],
        [...$delayedScores, ...$reservedPayloads],
        $readyPayloads,
    );
    $redis = mockDashboardContract(RedisFactory::class);
    dashboardReturnsUsing(
        $redis,
        'connection',
        static fn (?string $name = null): Connection => $horizonConnection,
    );
    app()->instance(RedisFactory::class, $redis);

    $queue = new PendingJobOrderingRedisQueue($queueConnection);
    $queues = mockDashboardContract(QueueFactory::class);
    dashboardReturnsUsing($queues, 'connection', static fn (?string $name = null): RedisQueue => $queue);
    app()->instance(QueueFactory::class, $queues);

    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [(object) ['processes' => $processes]]);
    app()->instance(SupervisorRepository::class, $supervisors);
    app()->forgetInstance(JobsData::class);

    return $hydration;
}

function pendingOrderingJob(
    int $index,
    string $id,
    float $pushedAt,
    ?float $releaseAt = null,
): HorizonJob {
    $job = horizonJob($index, $id);
    $job->name = 'App\\Jobs\\PendingJob'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);
    $job->status = 'pending';
    $job->completed_at = null;
    $delay = $releaseAt === null ? null : max(1, (int) ($releaseAt - $pushedAt));
    $job->delay = $delay;
    $job->updated_at = $delay === null ? null : (string) $pushedAt;
    $job->payload = json_encode([
        'uuid' => $id,
        'displayName' => $job->name,
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'pushedAt' => $pushedAt,
        'createdAt' => (int) $pushedAt,
        'delay' => $delay,
        'data' => [
            'commandName' => $job->name,
            'command' => serialize((object) ['delay' => $delay]),
        ],
    ], JSON_THROW_ON_ERROR);

    return $job;
}

function delayedOrderingPayload(string $id, float $createdAt, int $delay): string
{
    return json_encode([
        'uuid' => $id,
        'displayName' => 'App\\Jobs\\ImportFeed',
        'createdAt' => (int) $createdAt,
        'delay' => $delay,
        'data' => ['commandName' => 'App\\Jobs\\ImportFeed'],
    ], JSON_THROW_ON_ERROR);
}
