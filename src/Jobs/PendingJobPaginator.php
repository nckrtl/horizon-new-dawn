<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Pagination\Cursor;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\ProvisioningPlan;
use Throwable;

final readonly class PendingJobPaginator
{
    public function __construct(
        private RedisFactory $redis,
        private QueueFactory $queues,
        private SupervisorRepository $supervisors,
    ) {}

    public function page(int|string|null $cursor, int $limit): PendingJobPage
    {
        $current = $this->normalizeCursor($cursor);

        if ($limit <= 0) {
            return new PendingJobPage([], $current, null);
        }

        $ordered = $this->ordered();
        $startingAt = $this->startingAt($ordered, $current);
        $rows = array_slice($ordered, $startingAt, $limit);
        $hasMore = count($ordered) > $startingAt + count($rows);
        $last = $rows[array_key_last($rows)] ?? null;

        return new PendingJobPage(
            ids: array_column($rows, 'id'),
            current: $current,
            next: $hasMore && is_array($last) ? $this->encodeCursor($last) : null,
        );
    }

    /** @return array<int, string> */
    public function ids(int $startingAt, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_column(
            array_slice($this->ordered(), max(0, $startingAt), $limit),
            'id',
        );
    }

    /**
     * @return array<int, array{id: string, availableAt: float, pushedAt: float}>
     */
    private function ordered(): array
    {
        $scheduledScores = $this->scheduledScores();
        $ordered = [];

        foreach ($this->pendingScores() as $id => $score) {
            $pushedAt = abs($score);
            $ordered[] = [
                'id' => $id,
                'availableAt' => $scheduledScores[$id] ?? $pushedAt,
                'pushedAt' => $pushedAt,
            ];
        }

        usort(
            $ordered,
            $this->compare(...),
        );

        return $ordered;
    }

    /**
     * @param  array<int, array{id: string, availableAt: float, pushedAt: float}>  $ordered
     */
    private function startingAt(array $ordered, int|string|null $cursor): int
    {
        if (is_int($cursor) || (is_string($cursor) && preg_match('/^-?\d+$/D', $cursor) === 1)) {
            return max(0, (int) $cursor + 1);
        }

        $position = $this->decodeCursor($cursor);

        if ($position === null) {
            return 0;
        }

        foreach ($ordered as $index => $row) {
            if ($this->compare($row, $position) > 0) {
                return $index;
            }
        }

        return count($ordered);
    }

    private function normalizeCursor(int|string|null $cursor): int|string
    {
        if (is_int($cursor)) {
            return $cursor;
        }

        if (is_string($cursor) && preg_match('/^-?\d+$/D', $cursor) === 1) {
            return (int) $cursor;
        }

        if (! is_string($cursor) || $this->decodeCursor($cursor) === null) {
            return -1;
        }

        return $cursor;
    }

    /**
     * @param  array{id: string, availableAt: float, pushedAt: float}  $left
     * @param  array{id: string, availableAt: float, pushedAt: float}  $right
     */
    private function compare(array $left, array $right): int
    {
        $order = $left['availableAt'] <=> $right['availableAt'];

        if ($order !== 0) {
            return $order;
        }

        $order = $left['pushedAt'] <=> $right['pushedAt'];

        return $order !== 0 ? $order : strcmp($left['id'], $right['id']);
    }

    /**
     * @param  array{id: string, availableAt: float, pushedAt: float}  $row
     */
    private function encodeCursor(array $row): string
    {
        return (new Cursor([
            'version' => 1,
            'availableAt' => $row['availableAt'],
            'pushedAt' => $row['pushedAt'],
            'id' => $row['id'],
        ]))->encode();
    }

    /**
     * @return array{id: string, availableAt: float, pushedAt: float}|null
     */
    private function decodeCursor(int|string|null $encoded): ?array
    {
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        try {
            $cursor = Cursor::fromEncoded($encoded);

            if ($cursor === null || $cursor->pointsToNextItems() !== true) {
                return null;
            }

            [$version, $availableAt, $pushedAt, $id] = $cursor->parameters([
                'version',
                'availableAt',
                'pushedAt',
                'id',
            ]);
        } catch (Throwable) {
            return null;
        }

        if (
            $version !== 1
            || ! is_numeric($availableAt)
            || ! is_numeric($pushedAt)
            || ! is_string($id)
            || $id === ''
        ) {
            return null;
        }

        return [
            'id' => $id,
            'availableAt' => (float) $availableAt,
            'pushedAt' => (float) $pushedAt,
        ];
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
