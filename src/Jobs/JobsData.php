<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Jobs\Data\JobDetailData;
use NckRtl\HorizonNewDawn\Jobs\Data\JobPageData;
use NckRtl\HorizonNewDawn\Jobs\Data\JobRowData;
use Throwable;

final readonly class JobsData
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private JobRepository $jobs,
        private ?RedisFactory $redis = null,
    ) {}

    public function page(JobListType $type, int $afterIndex): JobPageData
    {
        try {
            $cursor = (string) $afterIndex;
            [$jobs, $total] = match ($type) {
                JobListType::Pending => [$this->jobs->getPending($cursor), $this->jobs->countPending()],
                JobListType::Completed => [$this->jobs->getCompleted($cursor), $this->jobs->countCompleted()],
                JobListType::Silenced => [$this->jobs->getSilenced($cursor), $this->jobs->countSilenced()],
            };

            $items = [];

            foreach ($jobs as $job) {
                if (! is_object($job)) {
                    continue;
                }

                $row = $this->row($job);

                if ($row !== null) {
                    $items[] = $row;
                }
            }

            return new JobPageData(
                available: true,
                items: $items,
                total: $total,
                current: $afterIndex,
                next: $jobs->count() === self::PAGE_SIZE ? $this->lastIndex($jobs) : null,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new JobPageData(
                available: false,
                items: [],
                total: 0,
                current: $afterIndex,
                next: null,
                message: 'Horizon jobs are currently unavailable.',
            );
        }
    }

    public function find(string $id): ?JobDetailData
    {
        try {
            $job = $this->jobs->getJobs([$id])->first();

            return is_object($job) ? $this->detail($job) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function batchId(object $job): ?string
    {
        $payload = $this->decodePayload($job->payload ?? null);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $batchId = $data['batchId'] ?? null;

        return is_string($batchId) && $batchId !== '' ? $batchId : null;
    }

    public function row(
        object $job,
        bool $retried = false,
        bool $retryCompleted = false,
        int $retryCount = 0,
        ?string $latestRetryStatus = null,
        bool $retryEligible = false,
    ): ?JobRowData {
        $id = $job->id ?? null;
        $name = $job->name ?? null;

        if (! is_string($id) || $id === '' || ! is_string($name) || $name === '') {
            return null;
        }

        $payload = $this->decodePayload($job->payload ?? null);
        $pushedAt = $this->timestamp($payload['pushedAt'] ?? null);
        $decodedCommand = $this->decodedCommand($payload);
        $reservedAt = $this->timestamp($job->reserved_at ?? null);
        $completedAt = $this->timestamp($job->completed_at ?? null);
        $failedAt = $this->timestamp($job->failed_at ?? null);
        $runtime = $this->runtime($reservedAt, $completedAt ?? $failedAt);

        return new JobRowData(
            id: $id,
            index: is_numeric($job->index ?? null) ? (int) $job->index : 0,
            name: $name,
            shortName: Str::afterLast($name, '\\'),
            connection: is_string($job->connection ?? null) ? $job->connection : 'default',
            queue: is_string($job->queue ?? null) ? $job->queue : 'default',
            status: is_string($job->status ?? null) ? $job->status : 'unknown',
            tags: $this->tags($payload),
            attempts: is_numeric($payload['attempts'] ?? null) ? (int) $payload['attempts'] : 0,
            retryOf: is_string($payload['retry_of'] ?? null) ? $payload['retry_of'] : null,
            delay: $this->delaySeconds($job->delay ?? null, $decodedCommand, $pushedAt),
            pushedAt: $pushedAt,
            reservedAt: $reservedAt,
            completedAt: $completedAt,
            failedAt: $failedAt,
            runtime: $runtime,
            occurredAt: $failedAt ?? $completedAt ?? $reservedAt ?? $pushedAt,
            retried: $retried,
            retryCompleted: $retryCompleted,
            retryCount: $retryCount,
            latestRetryStatus: $latestRetryStatus,
            retryEligible: $retryEligible,
        );
    }

    public function detail(object $job): ?JobDetailData
    {
        $row = $this->row($job);

        if ($row === null) {
            return null;
        }

        $payload = $this->decodePayload($job->payload ?? null);
        $decodedCommand = $this->decodedCommand($payload);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return new JobDetailData(
            id: $row->id,
            name: $row->name,
            shortName: $row->shortName,
            connection: $row->connection,
            queue: $row->queue,
            status: $row->status,
            tags: $row->tags,
            attempts: $row->attempts,
            retryOf: $row->retryOf,
            delay: $row->delay,
            delayedUntil: $this->delayedUntil($job, $row, $decodedCommand),
            batchId: is_string($data['batchId'] ?? null) ? $data['batchId'] : null,
            pushedAt: $row->pushedAt,
            reservedAt: $row->reservedAt,
            completedAt: $row->completedAt,
            failedAt: $row->failedAt,
            runtime: $row->runtime,
            payload: $this->safePayload($payload, $decodedCommand),
        );
    }

    /** @param Collection<int, mixed> $jobs */
    private function lastIndex(Collection $jobs): ?int
    {
        $last = $jobs->last();

        return is_object($last) && is_numeric($last->index ?? null) ? (int) $last->index : null;
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (! is_string($payload) || $payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function tags(array $payload): array
    {
        $tags = $payload['tags'] ?? [];

        return is_array($tags)
            ? array_values(array_filter($tags, is_string(...)))
            : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<array-key, mixed>|null  $decodedCommand
     * @return array<string, mixed>
     */
    private function safePayload(array $payload, ?array $decodedCommand): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        unset($data['command']);

        if ($decodedCommand !== null) {
            $data['decodedCommand'] = $decodedCommand;
        }

        return array_filter([
            'displayName' => is_string($payload['displayName'] ?? null) ? $payload['displayName'] : null,
            'job' => is_string($payload['job'] ?? null) ? $payload['job'] : null,
            'uuid' => is_string($payload['uuid'] ?? null) ? $payload['uuid'] : null,
            'maxTries' => is_numeric($payload['maxTries'] ?? null) ? (int) $payload['maxTries'] : null,
            'timeout' => is_numeric($payload['timeout'] ?? null) ? (int) $payload['timeout'] : null,
            'data' => $data,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function timestamp(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Decode Horizon's serialized command without instantiating application classes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>|null
     */
    private function decodedCommand(array $payload): ?array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $serialized = $data['command'] ?? null;

        if (! is_string($serialized) || $serialized === '') {
            return null;
        }

        try {
            $command = @unserialize($serialized, ['allowed_classes' => false]);
        } catch (Throwable) {
            return null;
        }

        $normalized = $this->normalizeCommandValue($command);

        return is_array($normalized) ? $normalized : null;
    }

    private function normalizeCommandValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 8) {
            return '[Maximum depth reached]';
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach (array_slice($value, 0, 200, true) as $key => $item) {
                $normalized[$key] = $this->normalizeCommandValue($item, $depth + 1);
            }

            return $normalized;
        }

        if (! is_object($value)) {
            return null;
        }

        $normalized = [];

        foreach (array_slice(get_object_vars($value), 0, 200, true) as $key => $item) {
            $normalized[$this->normalizeCommandKey($key)] = $this->normalizeCommandValue(
                $item,
                $depth + 1,
            );
        }

        return $normalized;
    }

    private function normalizeCommandKey(int|string $key): int|string
    {
        if (! is_string($key)) {
            return $key;
        }

        if ($key === '__PHP_Incomplete_Class_Name') {
            return 'class';
        }

        $segments = explode("\0", $key);

        return end($segments) ?: $key;
    }

    /** @param array<array-key, mixed>|null $decodedCommand */
    private function initialDelayedUntil(
        ?array $decodedCommand,
        ?float $pushedAt,
        ?int $delay,
    ): ?float {
        $commandDelay = $decodedCommand['delay'] ?? null;

        if (is_array($commandDelay) && is_string($commandDelay['date'] ?? null)) {
            try {
                $timezone = is_string($commandDelay['timezone'] ?? null)
                    ? new DateTimeZone($commandDelay['timezone'])
                    : null;
                $date = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s.u',
                    $commandDelay['date'],
                    $timezone,
                );

                if ($date !== false) {
                    return (float) $date->getTimestamp();
                }
            } catch (Throwable) {
                return null;
            }
        }

        if ($pushedAt === null || $delay === null) {
            return null;
        }

        return $pushedAt + $delay;
    }

    /** @param array<array-key, mixed>|null $decodedCommand */
    private function delaySeconds(mixed $jobDelay, ?array $decodedCommand, ?float $pushedAt): ?int
    {
        $storedDelay = $this->numericDelay($jobDelay);

        if ($storedDelay !== null) {
            return $storedDelay;
        }

        $commandDelay = $decodedCommand['delay'] ?? null;

        if (is_numeric($commandDelay)) {
            return max(0, (int) $commandDelay);
        }

        $delayedUntil = $this->initialDelayedUntil($decodedCommand, $pushedAt, null);

        return $delayedUntil !== null && $pushedAt !== null
            ? max(0, (int) round($delayedUntil - $pushedAt))
            : null;
    }

    private function numericDelay(mixed $delay): ?int
    {
        return is_numeric($delay) ? max(0, (int) $delay) : null;
    }

    /** @param array<array-key, mixed>|null $decodedCommand */
    private function delayedUntil(
        object $job,
        JobRowData $row,
        ?array $decodedCommand,
    ): ?float {
        $releasedDelay = $this->numericDelay($job->delay ?? null);

        if ($releasedDelay === null) {
            return $this->initialDelayedUntil($decodedCommand, $row->pushedAt, $row->delay);
        }

        if ($row->status !== 'pending' || $releasedDelay === 0) {
            return null;
        }

        return $this->releasedUntil($job, $releasedDelay);
    }

    private function releasedUntil(object $job, int $delay): ?float
    {
        $updatedAt = $this->timestamp($job->updated_at ?? null)
            ?? $this->storedUpdatedAt($job->id ?? null);

        return $updatedAt === null ? null : $updatedAt + $delay;
    }

    private function storedUpdatedAt(mixed $id): ?float
    {
        if (! is_string($id) || $id === '' || $this->redis === null) {
            return null;
        }

        try {
            return $this->timestamp(
                $this->redis->connection('horizon')->hget($id, 'updated_at'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function runtime(?float $reservedAt, ?float $finishedAt): ?float
    {
        return $reservedAt !== null && $finishedAt !== null
            ? round(max(0, $finishedAt - $reservedAt), 3)
            : null;
    }
}
