<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use RuntimeException;
use Throwable;

final readonly class BatchRepositoryOverview
{
    private const string CACHE_KEY = 'horizon-new-dawn:batch-repository-overview:v1';

    private const int PAGE_SIZE = 100;

    public function __construct(
        private BatchRepository $batches,
        private CacheFactory $cache,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     previews: list<array{id: string, name: string, progress: int}>
     * }
     */
    public function get(): array
    {
        $cacheSeconds = intdiv(
            max(0, (int) config('horizon-new-dawn.poll_interval', 0)),
            1000,
        );

        if ($cacheSeconds === 0) {
            return $this->build();
        }

        try {
            $cache = $this->cache->store();
            $payload = $cache->remember(
                self::CACHE_KEY,
                $cacheSeconds,
                $this->build(...),
            );
            $overview = $this->normalize($payload);

            if ($overview !== null) {
                return $overview;
            }

            $cache->forget(self::CACHE_KEY);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->build();
    }

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     previews: list<array{id: string, name: string, progress: int}>
     * }
     */
    private function build(): array
    {
        $total = 0;
        $active = 0;
        $previews = [];
        $cursor = null;

        while (true) {
            $batches = $this->batches->get(self::PAGE_SIZE, $cursor);

            if ($batches === []) {
                return [
                    'total' => $total,
                    'active' => $active,
                    'previews' => $previews,
                ];
            }

            $total += count($batches);

            foreach ($batches as $batch) {
                if (! $this->isActive($batch)) {
                    continue;
                }

                $active++;

                if (count($previews) >= 3) {
                    continue;
                }

                $name = trim($batch->name);
                $previews[] = [
                    'id' => $batch->id,
                    'name' => $name === '' ? $batch->id : $name,
                    'progress' => (int) round($batch->progress()),
                ];
            }

            $cursor = $this->advanceCursor($batches, $cursor);
        }
    }

    private function isActive(Batch $batch): bool
    {
        return ! $batch->cancelled()
            && max(0, $batch->pendingJobs - $batch->failedJobs) > 0;
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

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     previews: list<array{id: string, name: string, progress: int}>
     * }|null
     */
    private function normalize(mixed $payload): ?array
    {
        if (! is_array($payload)
            || ! is_int($payload['total'] ?? null)
            || ! is_int($payload['active'] ?? null)
            || ! is_array($payload['previews'] ?? null)
        ) {
            return null;
        }

        $previews = [];

        foreach ($payload['previews'] as $preview) {
            if (! is_array($preview)
                || ! is_string($preview['id'] ?? null)
                || ! is_string($preview['name'] ?? null)
                || ! is_int($preview['progress'] ?? null)
            ) {
                return null;
            }

            $previews[] = [
                'id' => $preview['id'],
                'name' => $preview['name'],
                'progress' => $preview['progress'],
            ];
        }

        return [
            'total' => $payload['total'],
            'active' => $payload['active'],
            'previews' => $previews,
        ];
    }
}
