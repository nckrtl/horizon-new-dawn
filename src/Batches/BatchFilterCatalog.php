<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use NckRtl\HorizonNewDawn\Batches\Data\BatchFilterCatalogData;
use RuntimeException;
use Throwable;

final readonly class BatchFilterCatalog
{
    private const string CACHE_KEY = 'horizon-new-dawn:batch-filter-catalog:v1';

    private const int PAGE_SIZE = 100;

    public function __construct(
        private BatchRepository $batches,
        private BatchesData $data,
        private CacheFactory $cache,
    ) {}

    public function get(): BatchFilterCatalogData
    {
        $cacheSeconds = intdiv(max(0, (int) config('horizon-new-dawn.poll_interval', 0)), 1000);

        if ($cacheSeconds === 0) {
            return $this->build();
        }

        try {
            $cache = $this->cache->store();
            $payload = $cache->remember(
                self::CACHE_KEY,
                $cacheSeconds,
                fn (): array => $this->build()->toArray(),
            );
            $catalog = $this->normalize($payload);

            if ($catalog !== null) {
                return $catalog;
            }

            $cache->forget(self::CACHE_KEY);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->build();
    }

    private function build(): BatchFilterCatalogData
    {
        try {
            $cursor = null;
            $queues = [];
            $connections = [];

            while (true) {
                $page = $this->batches->get(self::PAGE_SIZE, $cursor);

                if ($page === []) {
                    return new BatchFilterCatalogData(
                        available: true,
                        queues: $this->sortedValues($queues),
                        connections: $this->sortedValues($connections),
                    );
                }

                foreach ($page as $batch) {
                    $queues[] = $this->data->queue($batch);

                    $connection = $this->data->connection($batch);

                    if ($connection !== null) {
                        $connections[] = $connection;
                    }
                }

                $cursor = $this->advanceCursor($page, $cursor);
            }
        } catch (Throwable $exception) {
            report($exception);

            return new BatchFilterCatalogData(
                available: false,
                queues: [],
                connections: [],
            );
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

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function sortedValues(array $values): array
    {
        $values = array_values(array_unique(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));

        usort($values, static fn (string $left, string $right): int => strcasecmp($left, $right));

        return $values;
    }

    private function normalize(mixed $payload): ?BatchFilterCatalogData
    {
        if (! is_array($payload) || ! is_bool($payload['available'] ?? null)) {
            return null;
        }

        $queues = $this->normalizeValues($payload['queues'] ?? null);
        $connections = $this->normalizeValues($payload['connections'] ?? null);

        if ($queues === null || $connections === null) {
            return null;
        }

        return new BatchFilterCatalogData(
            available: $payload['available'],
            queues: $queues,
            connections: $connections,
        );
    }

    /** @return list<string>|null */
    private function normalizeValues(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        foreach ($values as $value) {
            if (! is_string($value)) {
                return null;
            }
        }

        return $this->sortedValues($values);
    }
}
