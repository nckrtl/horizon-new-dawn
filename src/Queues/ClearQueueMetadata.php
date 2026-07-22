<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Queues;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use NckRtl\HorizonNewDawn\Jobs\ForgetsPendingJob;

/**
 * Removes pending/reserved Horizon job hashes for one connection+queue pair.
 *
 * Unlike Horizon's JobRepository::purge($queue), this never matches by queue
 * name alone, so identically named queues on other connections stay intact.
 */
final readonly class ClearQueueMetadata implements ClearsQueueMetadata, ForgetsPendingJob
{
    public function __construct(private RedisFactory $redis) {}

    public function purgePending(string $connection, string $queue): int
    {
        $count = 0;
        $cursor = 0;
        $redis = $this->horizonConnection();
        $prefix = (string) config('horizon.prefix', 'horizon:');

        do {
            $result = $this->evaluate(
                $redis,
                $this->purgeScript(),
                2,
                'pending_jobs',
                'recent_jobs',
                $prefix,
                $queue,
                $connection,
                (string) $cursor,
            );

            if (! is_array($result) || count($result) < 2) {
                break;
            }

            $count += (int) $result[0];
            $cursor = $result[1];
        } while ((string) $cursor !== '0');

        return $count;
    }

    /** @param array<int, string> $tags */
    public function forgetPending(string $id, array $tags): bool
    {
        $removed = $this->evaluate(
            $this->horizonConnection(),
            <<<'LUA'
                local hashkey = ARGV[1] .. ARGV[2]

                if redis.call('hget', hashkey, 'status') ~= 'pending' then
                    return 0
                end

                redis.call('zrem', KEYS[1], ARGV[2])
                redis.call('zrem', KEYS[2], ARGV[2])

                for i = 3, #ARGV do
                    redis.call('zrem', ARGV[1] .. ARGV[i], ARGV[2])
                end

                redis.call('del', hashkey)

                return 1
            LUA,
            2,
            'pending_jobs',
            'recent_jobs',
            (string) config('horizon.prefix', 'horizon:'),
            $id,
            ...$tags,
        );

        return (int) $removed === 1;
    }

    private function horizonConnection(): Connection
    {
        return $this->redis->connection('horizon');
    }

    private function evaluate(Connection $redis, string $script, int $numberOfKeys, mixed ...$arguments): mixed
    {
        // Mirror Horizon's connection->eval(script, numkeys, ...) for each Redis driver.
        // PhpRedisConnection packages keys/args as an array; Predis uses a flat argument list.
        if ($redis instanceof PhpRedisConnection) {
            return $redis->command('eval', [$script, $arguments, $numberOfKeys]);
        }

        return $redis->command('eval', [$script, $numberOfKeys, ...$arguments]);
    }

    private function purgeScript(): string
    {
        return <<<'LUA'
            local count = 0
            local cursor = ARGV[4]

            local scanner = redis.call('zscan', KEYS[1], cursor)
            cursor = scanner[1]

            for i = 1, #scanner[2], 2 do
                local jobid = scanner[2][i]
                local hashkey = ARGV[1] .. jobid
                local job = redis.call('hmget', hashkey, 'status', 'queue', 'connection')

                if ((job[1] == 'reserved' or job[1] == 'pending') and job[2] == ARGV[2] and job[3] == ARGV[3]) then
                    redis.call('zrem', KEYS[1], jobid)
                    redis.call('zrem', KEYS[2], jobid)
                    redis.call('del', hashkey)
                    count = count + 1
                end
            end

            return {count, cursor}
LUA;
    }
}
