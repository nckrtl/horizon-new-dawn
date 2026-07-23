<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\ProvisioningPlan;

final readonly class PendingJobPaginator
{
    public function __construct(
        private RedisFactory $redis,
        private QueueFactory $queues,
        private SupervisorRepository $supervisors,
    ) {}

    /** @return array<int, string> */
    public function ids(int $startingAt, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $pendingScores = $this->pendingScores();
        $scheduledScores = $this->scheduledScores();
        $ordered = [];

        foreach ($pendingScores as $id => $score) {
            $pushedAt = abs($score);
            $ordered[] = [
                'id' => $id,
                'availableAt' => $scheduledScores[$id] ?? $pushedAt,
                'pushedAt' => $pushedAt,
            ];
        }

        usort(
            $ordered,
            static function (array $left, array $right): int {
                $order = $left['availableAt'] <=> $right['availableAt'];

                if ($order !== 0) {
                    return $order;
                }

                $order = $left['pushedAt'] <=> $right['pushedAt'];

                return $order !== 0 ? $order : strcmp($left['id'], $right['id']);
            },
        );

        return array_column(
            array_slice($ordered, max(0, $startingAt), $limit),
            'id',
        );
    }

    /** @return array<string, float> */
    private function pendingScores(): array
    {
        return $this->descendingScores(
            $this->redis->connection('horizon'),
            'pending_jobs',
        );
    }

    /** @return array<string, float> */
    private function scheduledScores(): array
    {
        $scores = [];

        foreach ($this->queueTargets() as [$connection, $queueName]) {
            $queue = $this->queues->connection($connection);

            if (! $queue instanceof RedisQueue) {
                continue;
            }

            $connection = $queue->getConnection();
            $queueKey = $queue->getQueue($queueName);
            $entries = $this->ascendingScores($connection, $queueKey.':delayed');

            foreach ($entries as $payload => $releaseAt) {
                $this->addScheduledScore($scores, $payload, $releaseAt);
            }

            foreach ($this->listMembers($connection, $queueKey) as $payload) {
                $this->addPayloadSchedule($scores, $payload);
            }

            foreach ($this->sortedSetMembers($connection, $queueKey.':reserved') as $payload) {
                $this->addPayloadSchedule($scores, $payload);
            }
        }

        return $scores;
    }

    /** @param array<string, float> $scores */
    private function addScheduledScore(array &$scores, string $payload, float $releaseAt): void
    {
        $id = $this->payloadId($payload);

        if ($id !== null) {
            $scores[$id] = isset($scores[$id])
                ? min($scores[$id], $releaseAt)
                : $releaseAt;
        }
    }

    /** @param array<string, float> $scores */
    private function addPayloadSchedule(array &$scores, string $payload): void
    {
        $schedule = $this->payloadSchedule($payload);

        if ($schedule === null) {
            return;
        }

        $scores[$schedule['id']] = isset($scores[$schedule['id']])
            ? min($scores[$schedule['id']], $schedule['releaseAt'])
            : $schedule['releaseAt'];
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function queueTargets(): array
    {
        $targets = [];

        foreach ($this->supervisors->all() as $supervisor) {
            $processes = is_array($supervisor->processes ?? null)
                ? $supervisor->processes
                : [];

            foreach (array_keys($processes) as $descriptor) {
                if (! is_string($descriptor) || ! str_contains($descriptor, ':')) {
                    continue;
                }

                [$connection, $queueNames] = explode(':', $descriptor, 2);
                $this->addTargets($targets, $connection, $queueNames);
            }
        }

        if ($targets === []) {
            $environment = config('horizon.env') ?? config('app.env');
            $plans = ProvisioningPlan::get('horizon-new-dawn')->toSupervisorOptions();

            foreach ($plans as $pattern => $supervisors) {
                if (! is_string($environment) || ! Str::is((string) $pattern, $environment)) {
                    continue;
                }

                foreach ($supervisors as $supervisor) {
                    $this->addTargets(
                        $targets,
                        is_string($supervisor->connection ?? null) ? $supervisor->connection : '',
                        is_string($supervisor->queue ?? null) ? $supervisor->queue : '',
                    );
                }

                break;
            }
        }

        return array_values($targets);
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $targets
     */
    private function addTargets(array &$targets, string $connection, string $queueNames): void
    {
        $connection = trim($connection);

        if ($connection === '') {
            return;
        }

        foreach (array_unique(explode(',', $queueNames)) as $queueName) {
            $queueName = trim($queueName);

            if ($queueName !== '') {
                $targets[$connection."\0".$queueName] = [$connection, $queueName];
            }
        }
    }

    /** @return array<string, float> */
    private function descendingScores(Connection $connection, string $key): array
    {
        $scores = $connection instanceof PhpRedisConnection
            ? $connection->zrevrange($key, 0, -1, true)
            : $connection->zrevrange($key, 0, -1, ['withscores' => true]);

        return $this->normalizeScores($scores);
    }

    /** @return array<string, float> */
    private function ascendingScores(Connection $connection, string $key): array
    {
        $scores = $connection instanceof PhpRedisConnection
            ? $connection->zrange($key, 0, -1, true)
            : $connection->zrange($key, 0, -1, ['withscores' => true]);

        return $this->normalizeScores($scores);
    }

    /** @return array<int, string> */
    private function listMembers(Connection $connection, string $key): array
    {
        return $this->normalizeMembers($connection->lrange($key, 0, -1));
    }

    /** @return array<int, string> */
    private function sortedSetMembers(Connection $connection, string $key): array
    {
        return $this->normalizeMembers($connection->zrange($key, 0, -1));
    }

    /** @return array<string, float> */
    private function normalizeScores(mixed $scores): array
    {
        if (! is_array($scores)) {
            return [];
        }

        $normalized = [];

        foreach ($scores as $member => $score) {
            if (is_string($member) && is_numeric($score)) {
                $normalized[$member] = (float) $score;
            }
        }

        return $normalized;
    }

    /** @return array<int, string> */
    private function normalizeMembers(mixed $members): array
    {
        return is_array($members)
            ? array_values(array_filter($members, is_string(...)))
            : [];
    }

    private function payloadId(string $payload): ?string
    {
        $decoded = $this->decodePayload($payload);
        $id = $decoded['uuid'] ?? $decoded['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array{id: string, releaseAt: float}|null */
    private function payloadSchedule(string $payload): ?array
    {
        $decoded = $this->decodePayload($payload);

        if (isset($decoded['retry_of'])) {
            return null;
        }

        $id = $decoded['uuid'] ?? $decoded['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $madeAvailableAt = $decoded['horizonNewDawn']['madeAvailableAt'] ?? null;

        if (is_numeric($madeAvailableAt) && (float) $madeAvailableAt > 0) {
            return [
                'id' => $id,
                'releaseAt' => (float) $madeAvailableAt,
            ];
        }

        $createdAt = $decoded['createdAt'] ?? $decoded['pushedAt'] ?? null;
        $delay = $decoded['delay'] ?? null;

        if (
            ! is_numeric($createdAt)
            || ! is_numeric($delay)
            || (float) $delay <= 0
        ) {
            return null;
        }

        return [
            'id' => $id,
            'releaseAt' => (float) $createdAt + (float) $delay,
        ];
    }

    /** @return array<string, mixed> */
    private function decodePayload(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
