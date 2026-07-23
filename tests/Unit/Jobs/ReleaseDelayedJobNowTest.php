<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\RedisQueue;
use NckRtl\HorizonNewDawn\Jobs\Actions\ReleaseDelayedJobNow;
use NckRtl\HorizonNewDawn\Jobs\ReleaseDelayedJobNowResult;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('ReleaseDelayedJobNow', function (): void {
    it('makes the exact delayed payload immediately eligible and migrates it through Horizon', function (): void {
        Date::setTestNow('2026-07-23 12:34:56');

        $job = horizonJob(0, 'delayed-1');
        $job->status = 'pending';
        $job->connection = 'redis';
        $job->queue = 'imports';

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $redis = new ReleaseDelayedJobRedisConnection(1);
        $queue = new ReleaseDelayedJobRedisQueue($redis);
        $result = (new ReleaseDelayedJobNow(
            $jobs,
            new ReleaseDelayedJobQueueManager(app(), $queue),
        ))->handle($job->id);

        expect($result)->toBe(ReleaseDelayedJobNowResult::Released)
            ->and($redis->commands)->toHaveCount(1)
            ->and($queue->migrations)->toBe([
                ['queues:imports:delayed', 'queues:imports'],
            ]);

        [$method, $arguments] = $redis->commands[0];
        $replacementPayload = json_decode((string) ($arguments[5] ?? ''), true);

        expect($method)->toBe('eval')
            ->and($arguments[0] ?? '')->toContain("redis.call('zscore', KEYS[1], ARGV[1])")
            ->and($arguments[0] ?? '')->toContain("redis.call('zrem', KEYS[1], ARGV[1])")
            ->and($arguments[0] ?? '')->toContain("redis.call('zadd', KEYS[1], ARGV[2], ARGV[3])")
            ->and($arguments[1] ?? null)->toBe(1)
            ->and($arguments[2] ?? null)->toBe('queues:imports:delayed')
            ->and($arguments[3] ?? null)->toBe($job->payload)
            ->and($arguments[4] ?? null)->toBe((string) Date::now()->getTimestamp())
            ->and($replacementPayload['displayName'] ?? null)->toBe('App\\Jobs\\ImportFeed')
            ->and($replacementPayload['horizonNewDawn']['madeAvailableAt'] ?? null)
            ->toBe(Date::now()->getTimestamp());
    });

    it('does not migrate a job that left the delayed set before the action ran', function (): void {
        $job = horizonJob(0, 'delayed-1');
        $job->status = 'pending';

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $redis = new ReleaseDelayedJobRedisConnection(0);
        $queue = new ReleaseDelayedJobRedisQueue($redis);
        $result = (new ReleaseDelayedJobNow(
            $jobs,
            new ReleaseDelayedJobQueueManager(app(), $queue),
        ))->handle($job->id);

        expect($result)->toBe(ReleaseDelayedJobNowResult::NotDelayed)
            ->and($queue->migrations)->toBe([]);
    });
});

final class ReleaseDelayedJobQueueManager extends QueueManager
{
    public function __construct($app, private readonly Queue $queue)
    {
        parent::__construct($app);
    }

    public function connection($name = null): Queue
    {
        return $this->queue;
    }
}

final class ReleaseDelayedJobRedisQueue extends RedisQueue
{
    /** @var array<int, array{0: string, 1: string}> */
    public array $migrations = [];

    public function __construct(private readonly Connection $redisConnection) {}

    public function getConnection(): Connection
    {
        return $this->redisConnection;
    }

    public function getQueue($queue): string
    {
        return 'queues:'.($queue ?: 'default');
    }

    /** @return array<int, string> */
    public function migrateExpiredJobs($from, $to): array
    {
        $this->migrations[] = [$from, $to];

        return [];
    }
}

final class ReleaseDelayedJobRedisConnection extends Connection
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $commands = [];

    public function __construct(private readonly int $result) {}

    /** @param array<int, string>|string $channels */
    public function createSubscription($channels, $callback, $method = 'subscribe'): void {}

    /** @param array<int, mixed> $parameters */
    public function command($method, array $parameters = []): int
    {
        $this->commands[] = [$method, $parameters];

        return $this->result;
    }
}
