<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use NckRtl\HorizonNewDawn\Batches\Data\BatchDetailData;
use NckRtl\HorizonNewDawn\Batches\Data\BatchPageData;
use NckRtl\HorizonNewDawn\Batches\Data\BatchRowData;
use RuntimeException;
use Throwable;

final readonly class BatchesData
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private BatchRepository $batches,
        private BatchJobsData $batchJobs,
    ) {}

    public function page(
        ?string $beforeId,
        ?string $query,
        ?string $queue = null,
        ?string $connection = null,
        ?BatchCreatedRange $created = null,
    ): BatchPageData {
        try {
            if (($query !== null && trim($query) !== '')
                || $queue !== null
                || $connection !== null
                || $created !== null
            ) {
                [$batches, $next] = $this->filteredPage(
                    $beforeId,
                    $query,
                    $queue,
                    $connection,
                    $created,
                );
            } else {
                [$batches, $next] = $this->repositoryPage($beforeId);
            }

            $rows = array_values(array_map($this->row(...), $batches));

            return new BatchPageData(
                available: true,
                batches: $rows,
                current: $beforeId,
                next: $next,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new BatchPageData(
                available: false,
                batches: [],
                current: $beforeId,
                next: null,
                message: 'Batches are currently unavailable.',
            );
        }
    }

    public function find(string $id): ?BatchDetailData
    {
        try {
            $batch = $this->batches->find($id);

            if ($batch === null) {
                return null;
            }

            $row = $this->row($batch);
            $jobLists = $this->batchJobs->forBatch($batch);

            return new BatchDetailData(
                id: $row->id,
                name: $row->name,
                displayName: $row->displayName,
                connection: $row->connection,
                queue: $row->queue,
                totalJobs: $row->totalJobs,
                pendingJobs: $row->pendingJobs,
                failedJobs: $row->failedJobs,
                failedJobAttempts: $row->failedJobAttempts,
                processedJobs: $row->processedJobs,
                progress: $row->progress,
                status: $row->status,
                createdAt: $row->createdAt,
                cancelledAt: $row->cancelledAt,
                finishedAt: $row->finishedAt,
                jobs: $jobLists,
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function row(Batch $batch): BatchRowData
    {
        $name = trim($batch->name);
        $processedJobs = $batch->processedJobs();
        $failedJobs = min(max(0, $batch->pendingJobs), max(0, $batch->failedJobs));
        $pendingJobs = max(0, $batch->pendingJobs - $failedJobs);
        $attribution = $this->attribution($batch);
        $status = match (true) {
            $batch->cancelled() => 'cancelled',
            $processedJobs === $batch->totalJobs => 'finished',
            $batch->failedJobs > 0 => 'failures',
            default => 'pending',
        };

        return new BatchRowData(
            id: $batch->id,
            name: $name === '' ? null : $name,
            displayName: $name === '' ? $batch->id : $name,
            connection: $attribution['connection'],
            queue: $attribution['queue'],
            totalJobs: $batch->totalJobs,
            pendingJobs: $pendingJobs,
            failedJobs: $failedJobs,
            failedJobAttempts: max(0, $batch->failedJobs),
            processedJobs: $processedJobs,
            progress: (int) $batch->progress(),
            status: $status,
            createdAt: $batch->createdAt->getTimestamp(),
            cancelledAt: $this->timestamp($batch->cancelledAt),
            finishedAt: $this->timestamp($batch->finishedAt),
        );
    }

    public function queue(Batch $batch): string
    {
        return $this->attribution($batch)['queue'];
    }

    public function connection(Batch $batch): ?string
    {
        return $this->attribution($batch)['connection'];
    }

    /** @return array{connection: ?string, queue: string} */
    private function attribution(Batch $batch): array
    {
        $connection = $this->option($batch, 'connection');

        if ($connection === null) {
            $configuredDefault = config('queue.default');
            $connection = is_string($configuredDefault) && $configuredDefault !== ''
                ? $configuredDefault
                : null;
        }

        $queue = $this->option($batch, 'queue') ?? $this->configuredQueue($connection) ?? 'default';

        return ['connection' => $connection, 'queue' => $queue];
    }

    private function configuredQueue(?string $connection): ?string
    {
        if ($connection === null) {
            return null;
        }

        $connections = config('queue.connections', []);

        if (! is_array($connections) || ! is_array($connections[$connection] ?? null)) {
            return null;
        }

        $queue = $connections[$connection]['queue'] ?? null;

        return is_string($queue) && $queue !== '' ? $queue : null;
    }

    private function option(Batch $batch, string $key): ?string
    {
        $value = $batch->options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array{0: array<int, Batch>, 1: ?string} */
    private function repositoryPage(?string $beforeId): array
    {
        $batches = [];
        $cursor = $beforeId;

        while (count($batches) < self::PAGE_SIZE) {
            $candidates = $this->batches->get(self::PAGE_SIZE - count($batches), $cursor);

            if ($candidates === []) {
                return [$batches, null];
            }

            $cursor = $this->advanceCursor($candidates, $cursor);
            array_push($batches, ...$candidates);
        }

        return [$batches, $cursor];
    }

    /** @return array{0: array<int, Batch>, 1: ?string} */
    private function filteredPage(
        ?string $beforeId,
        ?string $query,
        ?string $queue,
        ?string $connection,
        ?BatchCreatedRange $created,
    ): array {
        $matches = [];
        $cursor = $beforeId;
        $cutoff = $created?->cutoffTimestamp();

        while (true) {
            $candidates = $this->batches->get(self::PAGE_SIZE, $cursor);

            if ($candidates === []) {
                return [$matches, null];
            }

            $nextCursor = $this->advanceCursor($candidates, $cursor);

            foreach ($candidates as $batch) {
                $cursor = $batch->id;

                if (! $this->matchesFilters($batch, $query, $queue, $connection, $cutoff)) {
                    continue;
                }

                $matches[] = $batch;

                if (count($matches) === self::PAGE_SIZE) {
                    return [$matches, $cursor];
                }
            }

            $cursor = $nextCursor;
        }
    }

    /**
     * @param  array<int, Batch>  $batches
     */
    private function advanceCursor(array $batches, ?string $current): string
    {
        $batch = end($batches);

        if (! $batch instanceof Batch) {
            throw new RuntimeException('The batch repository returned an empty page.');
        }

        $cursor = $batch->id;

        if ($cursor === '' || ($current !== null && strcmp($cursor, $current) >= 0)) {
            throw new RuntimeException('The batch repository did not advance its pagination cursor.');
        }

        return $cursor;
    }

    private function matchesFilters(
        Batch $batch,
        ?string $query,
        ?string $queue,
        ?string $connection,
        ?int $createdAfter,
    ): bool {
        if ($query !== null && trim($query) !== '') {
            $needle = trim($query);

            if (mb_stripos($batch->name, $needle) === false && mb_stripos($batch->id, $needle) === false) {
                return false;
            }
        }

        $attribution = $this->attribution($batch);

        if ($queue !== null && $attribution['queue'] !== $queue) {
            return false;
        }

        if ($connection !== null && $attribution['connection'] !== $connection) {
            return false;
        }

        return $createdAfter === null || $batch->createdAt->getTimestamp() >= $createdAfter;
    }

    private function timestamp(?DateTimeInterface $value): ?int
    {
        return $value?->getTimestamp();
    }
}
