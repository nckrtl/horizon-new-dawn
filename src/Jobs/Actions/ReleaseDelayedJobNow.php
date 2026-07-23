<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs\Actions;

use Illuminate\Queue\QueueManager;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Support\Facades\Date;
use JsonException;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\RedisQueue;
use NckRtl\HorizonNewDawn\Jobs\ReleaseDelayedJobNowResult;
use RuntimeException;

final readonly class ReleaseDelayedJobNow
{
    public function __construct(
        private JobRepository $jobs,
        private QueueManager $queues,
    ) {}

    public function handle(string $id): ReleaseDelayedJobNowResult
    {
        $job = $this->jobs->getJobs([$id])->first();

        if (! is_object($job) || ($job->status ?? null) !== 'pending') {
            return ReleaseDelayedJobNowResult::NotDelayed;
        }

        $connection = $job->connection ?? null;
        $queueName = $job->queue ?? null;
        $payload = $job->payload ?? null;

        if (! is_string($connection) || $connection === '' ||
            ! is_string($queueName) || $queueName === '' ||
            ! is_string($payload) || $payload === '') {
            return ReleaseDelayedJobNowResult::NotDelayed;
        }

        $queue = $this->queues->connection($connection);

        if (! $queue instanceof RedisQueue) {
            throw new RuntimeException("Releasing delayed jobs is not supported for {$connection}.");
        }

        $redis = $queue->getConnection();
        $clusterQueueName = $this->isCluster($redis) && ! $this->hasHashTag($queueName)
            ? '{'.$queueName.'}'
            : $queueName;
        $ready = $queue->getQueue($clusterQueueName);
        $now = Date::now()->getTimestamp();
        $replacementPayload = $this->withMadeAvailableMetadata($payload, $now);

        if ($replacementPayload === null) {
            return ReleaseDelayedJobNowResult::NotDelayed;
        }

        $changed = $this->evaluate(
            $redis,
            <<<'LUA'
                if redis.call('zscore', KEYS[1], ARGV[1]) == false then
                    return 0
                end

                redis.call('zrem', KEYS[1], ARGV[1])
                redis.call('zadd', KEYS[1], ARGV[2], ARGV[3])

                return 1
            LUA,
            1,
            $ready.':delayed',
            $payload,
            (string) $now,
            $replacementPayload,
        );

        if ((int) $changed !== 1) {
            return ReleaseDelayedJobNowResult::NotDelayed;
        }

        $queue->migrateExpiredJobs($ready.':delayed', $ready);

        return ReleaseDelayedJobNowResult::Released;
    }

    private function withMadeAvailableMetadata(string $payload, int $timestamp): ?string
    {
        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return null;
            }

            $metadata = is_array($decoded['horizonNewDawn'] ?? null)
                ? $decoded['horizonNewDawn']
                : [];
            $metadata['madeAvailableAt'] = $timestamp;
            $decoded['horizonNewDawn'] = $metadata;

            return json_encode($decoded, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function hasHashTag(string $key): bool
    {
        $open = strpos($key, '{');

        if ($open === false) {
            return false;
        }

        $close = strpos($key, '}', $open + 1);

        return $close !== false && $close - $open > 1;
    }

    private function isCluster(RedisConnection $redis): bool
    {
        if (in_array('isCluster', get_class_methods($redis), true)) {
            return $redis->isCluster();
        }

        return $redis instanceof PhpRedisClusterConnection ||
            $redis instanceof PredisClusterConnection;
    }

    private function evaluate(
        RedisConnection $redis,
        string $script,
        int $numberOfKeys,
        mixed ...$arguments,
    ): mixed {
        if ($redis instanceof PhpRedisConnection) {
            return $redis->command('eval', [$script, $arguments, $numberOfKeys]);
        }

        return $redis->command('eval', [$script, $numberOfKeys, ...$arguments]);
    }
}
