<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Queues\ClearQueueMetadata;
use Predis\Client;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

$createRedisClient = static fn (string $prefix = ''): Client => new Client([
    'scheme' => 'tcp',
    'host' => (string) config('database.redis.default.host', '127.0.0.1'),
    'port' => (int) config('database.redis.default.port', 6379),
    'timeout' => 1,
], [
    'prefix' => $prefix,
]);

$redisIsUnavailable = function () use ($createRedisClient): bool {
    try {
        $createRedisClient()->ping();

        return false;
    } catch (Throwable) {
        return true;
    }
};

describe('ClearQueueMetadata', function () use ($createRedisClient, $redisIsUnavailable): void {
    it('forgets only metadata for a job that is still pending', function (): void {
        config()->set('horizon.prefix', 'horizon:');

        $redisConnection = new ForgetPendingJobRedisStub(1);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $redisConnection);

        expect((new ClearQueueMetadata($redis))->forgetPending('pending-1', ['tenant:1']))->toBeTrue();

        [$method, $parameters] = $redisConnection->commands[0];

        expect($method)->toBe('eval')
            ->and($parameters[0] ?? '')->toContain("hget', hashkey, 'status'")
            ->and($parameters[0] ?? '')->toContain("~= 'pending'")
            ->and($parameters[1] ?? null)->toBe(2)
            ->and($parameters[2] ?? null)->toBe('pending_jobs')
            ->and($parameters[3] ?? null)->toBe('recent_jobs')
            ->and($parameters[4] ?? null)->toBe('horizon:')
            ->and($parameters[5] ?? null)->toBe('pending-1')
            ->and($parameters[6] ?? null)->toBe('tenant:1');
    });

    it('purges a pending job after its recent job reference has expired', function () use ($createRedisClient): void {
        $prefix = 'horizon-new-dawn-test:'.Str::uuid().':';
        $client = $createRedisClient($prefix);

        config()->set('horizon.prefix', $prefix);

        $redisConnection = new PredisConnection($client);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $redisConnection);
        $targetId = 'target-'.Str::uuid();
        $otherConnectionId = 'other-connection-'.Str::uuid();
        $otherQueueId = 'other-queue-'.Str::uuid();

        try {
            foreach ([
                $targetId => ['connection' => 'redis', 'queue' => 'reports'],
                $otherConnectionId => ['connection' => 'sqs', 'queue' => 'reports'],
                $otherQueueId => ['connection' => 'redis', 'queue' => 'mail'],
            ] as $jobId => $job) {
                $redisConnection->command('zadd', ['pending_jobs', -1, $jobId]);
                $redisConnection->command('hmset', [$jobId, [
                    'status' => 'pending',
                    'queue' => $job['queue'],
                    'connection' => $job['connection'],
                ]]);
            }

            $removed = (new ClearQueueMetadata($redis))->purgePending('redis', 'reports');

            expect($removed)->toBe(1)
                ->and($redisConnection->command('zscore', ['pending_jobs', $targetId]))->toBeNull()
                ->and($redisConnection->command('exists', [$targetId]))->toBe(0)
                ->and($redisConnection->command('zscore', ['pending_jobs', $otherConnectionId]))->not->toBeNull()
                ->and($redisConnection->command('exists', [$otherConnectionId]))->toBe(1)
                ->and($redisConnection->command('zscore', ['pending_jobs', $otherQueueId]))->not->toBeNull()
                ->and($redisConnection->command('exists', [$otherQueueId]))->toBe(1);
        } finally {
            $redisConnection->command('del', [
                'pending_jobs',
                'recent_jobs',
                $targetId,
                $otherConnectionId,
                $otherQueueId,
            ]);
        }
    })->skip($redisIsUnavailable, 'A Redis-compatible server is not available.');

    it('purges pending and reserved jobs only for the given connection and queue', function (): void {
        config()->set('horizon.prefix', 'horizon:');

        $redisConnection = new ClearQueueMetadataRedisStub([[2, '0']]);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $redisConnection);

        $removed = (new ClearQueueMetadata($redis))->purgePending('redis', 'reports');
        $command = $redisConnection->commands[0] ?? null;

        expect($removed)->toBe(2)
            ->and($command)->not->toBeNull()
            ->and($command[0] ?? null)->toBe('eval')
            ->and($command[1][0] ?? '')->toContain('job[3] == ARGV[3]')
            ->and($command[1][0] ?? '')->toContain("job[1] == 'reserved' or job[1] == 'pending'")
            ->and($command[1][1] ?? null)->toBe(2)
            ->and($command[1][2] ?? null)->toBe('pending_jobs')
            ->and($command[1][3] ?? null)->toBe('recent_jobs')
            ->and($command[1][4] ?? null)->toBe('horizon:')
            ->and($command[1][5] ?? null)->toBe('reports')
            ->and($command[1][6] ?? null)->toBe('redis')
            ->and($command[1][7] ?? null)->toBe('0');
    });

    it('continues scanning until the Redis cursor returns to zero', function (): void {
        config()->set('horizon.prefix', 'horizon:');

        $redisConnection = new ClearQueueMetadataRedisStub([[1, '42'], [3, '0']]);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturns($redis, 'connection', $redisConnection);

        expect((new ClearQueueMetadata($redis))->purgePending('sqs', 'reports'))->toBe(4)
            ->and($redisConnection->commands)->toHaveCount(2);
    });

    it('packages PhpRedis eval arguments as script, argument array, and numkeys', function (): void {
        config()->set('horizon.prefix', 'horizon:');

        $redisConnection = new ClearQueueMetadataPhpRedisStub([[2, '0']]);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $redisConnection);

        $removed = (new ClearQueueMetadata($redis))->purgePending('redis', 'reports');

        expect($removed)->toBe(2)
            ->and($redisConnection->commands)->toHaveCount(1);

        [$method, $parameters] = $redisConnection->commands[0];

        expect($method)->toBe('eval')
            ->and($parameters)->toHaveCount(3)
            ->and($parameters[0])->toContain('job[3] == ARGV[3]')
            ->and($parameters[0])->toContain("job[1] == 'reserved' or job[1] == 'pending'")
            ->and($parameters[1])->toBe([
                'pending_jobs',
                'recent_jobs',
                'horizon:',
                'reports',
                'redis',
                '0',
            ])
            ->and($parameters[2])->toBe(2);
    });
});

/**
 * @phpstan-type EvalResult array{0: int, 1: string|int}
 */
final class ClearQueueMetadataRedisStub extends Connection
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $commands = [];

    /**
     * @param  array<int, EvalResult>  $responses
     */
    public function __construct(private array $responses) {}

    /**
     * @param  array<int|string, mixed>|string  $channels
     * @param  callable  $callback
     */
    /** @param array<int, string>|string $channels */
    public function createSubscription($channels, $callback, $method = 'subscribe'): void {}

    /**
     * @param  array<int, mixed>  $parameters
     * @return EvalResult
     */
    public function command($method, array $parameters = []): array
    {
        $this->commands[] = [$method, $parameters];

        return array_shift($this->responses) ?? [0, '0'];
    }
}

/**
 * @phpstan-type EvalResult array{0: int, 1: string|int}
 */
final class ClearQueueMetadataPhpRedisStub extends PhpRedisConnection
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $commands = [];

    /**
     * @param  array<int, EvalResult>  $responses
     */
    public function __construct(private array $responses)
    {
        // Client is unused: command() is fully overridden for packing assertions.
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return EvalResult
     */
    public function command($method, array $parameters = []): array
    {
        $this->commands[] = [$method, $parameters];

        return array_shift($this->responses) ?? [0, '0'];
    }
}

final class ForgetPendingJobRedisStub extends Connection
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
