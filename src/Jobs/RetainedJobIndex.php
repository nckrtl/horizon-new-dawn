<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

use Closure;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Support\RedisScript;
use Predis\Command\CommandInterface;
use Predis\Command\Processor\KeyPrefixProcessor;
use Predis\Response\Status;
use RuntimeException;
use Throwable;

final class RetainedJobIndex
{
    private const int SOURCE_CHUNK_SIZE = 1000;

    private const int CATALOG_SCAN_CHUNK_SIZE = 500;

    private const int TEMPORARY_KEY_TTL_SECONDS = 30;

    private const int SYNCHRONIZATION_LOCK_SECONDS = 120;

    private const int CANDIDATE_SYNCHRONIZATION_ATTEMPTS = 2;

    private const int PERSISTENT_READ_SYNCHRONIZATION_ATTEMPTS = 2;

    private const int POST_REBUILD_RECONCILIATION_ATTEMPTS = 2;

    private const int UNRESOLVED_REFERENCE_GRACE_SECONDS = 5;

    private const string LEGACY_GENERATION = 'legacy';

    /** @var array<int, string> */
    private const array GENERATIONS = ['a', 'b'];

    /** @var array<int, string> */
    private const array FACET_DIMENSIONS = [
        'job',
        'queue',
        'connection',
        'tag',
        'target',
    ];

    /** @var array<int, string> */
    private const array REQUIRED_CATALOG_DIMENSIONS = [
        'job',
        'queue',
        'connection',
    ];

    /** @var array<int, string> */
    private const array SINGLE_VALUED_CATALOG_DIMENSIONS = [
        'job',
        'queue',
        'connection',
        'target',
    ];

    private const string RELEASE_LOCK_SCRIPT = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('del', KEYS[1])
        end

        return 0
        LUA;

    private const string RENEW_LOCK_SCRIPT = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('expire', KEYS[1], ARGV[2])
        end

        return 0
        LUA;

    /** @var array<string, true> */
    private array $synchronized = [];

    /** @var array<string, true> */
    private array $trimmedSources = [];

    /** @var array<string, int> */
    private array $sourceSnapshotCounts = [];

    /** @var array<string, string> */
    private array $workingGenerations = [];

    /**
     * Generation ids resolved before MULTI/pipeline so key helpers never GET
     * on the shared connection while commands are queued. Nested depth tracks
     * re-entrant pipeline/transaction calls; cleared when the outer scope ends.
     *
     * @var array<string, string>|null
     */
    private ?array $atomicGenerationSnapshot = null;

    private int $atomicGenerationDepth = 0;

    private ?Connection $connection = null;

    private ?string $namespace = null;

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly JobRepository $jobs,
        private readonly int $hydrationChunkSize = 500,
        private readonly ?string $configuredNamespace = null,
    ) {}

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     */
    public function synchronize(
        RetainedJobType $type,
        bool $force = false,
        array $requiredFacets = [],
        array $requiredCatalogPartitions = [],
    ): void {
        $temporaryKeys = [];

        try {
            $this->synchronizedSourceSnapshot(
                $type,
                $force,
                $requiredFacets,
                $requiredCatalogPartitions,
                $temporaryKeys,
            );
            $this->ensureSynchronizationRevision($type);
        } finally {
            foreach ($temporaryKeys as $key) {
                $this->deleteQuietly($key);
            }
        }
    }

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     * @param  array<int, string>  $temporaryKeys
     */
    private function synchronizedSourceSnapshot(
        RetainedJobType $type,
        bool $force,
        array $requiredFacets,
        array $requiredCatalogPartitions,
        array &$temporaryKeys,
    ): string {
        if ($force || ! isset($this->trimmedSources[$type->value])) {
            $this->trimSource($type);
            $this->trimmedSources[$type->value] = true;
        }

        $sourceSnapshot = $this->materializeSourceSnapshot($type);
        $temporaryKeys[] = $sourceSnapshot;

        if (
            ! $force
            && $this->indexIsConsistent(
                $type,
                $requiredFacets,
                $requiredCatalogPartitions,
                $sourceSnapshot,
            )
        ) {
            $this->trimSource($type);
            $this->refreshSourceSnapshot($type, $sourceSnapshot);
            $this->synchronizeSnapshot(
                $type,
                $sourceSnapshot,
                false,
                $requiredFacets,
                $requiredCatalogPartitions,
            );

            return $sourceSnapshot;
        }

        $this->synchronizeGrowingSourceSnapshot(
            $type,
            $sourceSnapshot,
            $force,
            $requiredFacets,
            $requiredCatalogPartitions,
        );

        return $sourceSnapshot;
    }

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     */
    private function synchronizeSnapshot(
        RetainedJobType $type,
        string $sourceKey,
        bool $force,
        array $requiredFacets,
        array $requiredCatalogPartitions,
    ): void {
        if (
            ! $force
            && isset($this->synchronized[$type->value])
            && $this->indexIsConsistent(
                $type,
                $requiredFacets,
                $requiredCatalogPartitions,
                $sourceKey,
            )
        ) {
            return;
        }

        $this->assertNamespaceOwnership();

        if (
            ! $force
            && $this->indexIsConsistent(
                $type,
                $requiredFacets,
                $requiredCatalogPartitions,
                $sourceKey,
            )
        ) {
            $this->synchronized[$type->value] = true;

            return;
        }

        $lockToken = $this->acquireSynchronizationLock($type);

        if ($lockToken === null) {
            if (
                $force
                || ! $this->indexIsConsistent(
                    $type,
                    $requiredFacets,
                    $requiredCatalogPartitions,
                    $sourceKey,
                )
            ) {
                throw new RetainedJobIndexWarming;
            }

            $this->synchronized[$type->value] = true;

            return;
        }

        $heartbeat = fn () => $this->renewSynchronizationState(
            $type,
            $lockToken,
            $sourceKey,
        );
        try {
            $this->prepareWorkingGeneration($type, $heartbeat);
            $this->synchronizeSnapshotUnderLock(
                $type,
                $lockToken,
                $sourceKey,
                $requiredFacets,
                $requiredCatalogPartitions,
                $heartbeat,
            );

            $this->synchronized[$type->value] = true;
            $this->publishWorkingGeneration(
                $type,
                $lockToken,
                $sourceKey,
            );
        } finally {
            unset($this->workingGenerations[$type->value]);
            $this->releaseSynchronizationLock($type, $lockToken);
        }
    }

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     */
    private function synchronizeGrowingSourceSnapshot(
        RetainedJobType $type,
        string $sourceKey,
        bool $force,
        array $requiredFacets,
        array $requiredCatalogPartitions,
    ): void {
        $this->assertNamespaceOwnership();
        $lockToken = $this->acquireSynchronizationLock($type);

        if ($lockToken === null) {
            throw new RetainedJobIndexWarming;
        }

        $heartbeat = fn () => $this->renewSynchronizationState(
            $type,
            $lockToken,
            $sourceKey,
        );
        try {
            $this->prepareWorkingGeneration($type, $heartbeat);
            $this->synchronizeSnapshotUnderLock(
                $type,
                $lockToken,
                $sourceKey,
                $requiredFacets,
                $requiredCatalogPartitions,
                $heartbeat,
                allowIncomplete: true,
            );

            $this->trimSource($type);
            $this->refreshSourceSnapshot($type, $sourceKey, $heartbeat);
            $this->synchronizeSnapshotUnderLock(
                $type,
                $lockToken,
                $sourceKey,
                $requiredFacets,
                $requiredCatalogPartitions,
                $heartbeat,
            );

            $this->synchronized[$type->value] = true;
            $this->publishWorkingGeneration(
                $type,
                $lockToken,
                $sourceKey,
            );
        } finally {
            unset($this->workingGenerations[$type->value]);
            $this->releaseSynchronizationLock($type, $lockToken);
        }
    }

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     */
    private function synchronizeSnapshotUnderLock(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
        array $requiredFacets,
        array $requiredCatalogPartitions,
        Closure $heartbeat,
        bool $allowIncomplete = false,
    ): void {
        $this->reconcileProjection(
            $type,
            $lockToken,
            sourceKey: $sourceKey,
        );

        $heartbeat();

        if (! $this->indexStructureIsConsistent(
            $type,
            $requiredFacets,
            $requiredCatalogPartitions,
            $heartbeat,
        )) {
            $this->rebuildTypeIndex(
                $type,
                $lockToken,
                $requiredFacets,
                $sourceKey,
            );
        }

        for (
            $attempt = 0;
            $attempt < self::POST_REBUILD_RECONCILIATION_ATTEMPTS
            && ! $this->indexIsConsistent(
                $type,
                $requiredFacets,
                $requiredCatalogPartitions,
                $sourceKey,
                $heartbeat,
            );
            $attempt++
        ) {
            $this->reconcileProjection(
                $type,
                $lockToken,
                sourceKey: $sourceKey,
            );
            $heartbeat();
        }

        if ($this->indexIsConsistent(
            $type,
            $requiredFacets,
            $requiredCatalogPartitions,
            $sourceKey,
            $heartbeat,
        )) {
            return;
        }

        if ($allowIncomplete) {
            return;
        }

        throw new RuntimeException(
            'The retained job index could not be synchronized.',
        );
    }

    private function trimSource(RetainedJobType $type): void
    {
        if ($type === RetainedJobType::Failed) {
            $this->jobs->trimFailedJobs();

            return;
        }

        $this->jobs->trimRecentJobs();
    }

    private function reconcileProjection(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
    ): void {
        $sourceCount = $this->sortedSetCount($sourceKey);
        $projectionCount = $this->sortedSetCount(
            $this->projectionKey($type),
        );
        $unresolvedCount = $this->storedUnresolvedCount($type);
        $missingCount = $this->addMissingJobs(
            $type,
            $lockToken,
            $sourceKey,
        );

        // |coverage - source| = indexed + unresolved + missing - source.
        if (
            $projectionCount + $unresolvedCount + $missingCount
            > $sourceCount
        ) {
            $this->removeStaleJobs($type, $lockToken, $sourceKey);

            if ($unresolvedCount > 0) {
                $this->removeStaleUnresolvedReferences(
                    $type,
                    $lockToken,
                    $sourceKey,
                );
            }
        }
    }

    /**
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    public function pageIds(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds,
        ?RetainedJobPosition $after,
        int $limit,
        ?string $search = null,
    ): array {
        $search = trim($search ?? '');

        if ($facets === [] && $additionalIds === null && $search === '') {
            return $this->sourcePage(
                $type,
                $type->sourceKey(),
                $after,
                $limit,
            );
        }

        // Unfiltered reserved/state membership: score the small ID set against the
        // live source. Avoids withCandidateKey synchronization under source growth.
        if ($facets === [] && $additionalIds !== null && $search === '') {
            return $this->pageAdditionalIdsFromSource(
                $type,
                $additionalIds,
                $after,
                $limit,
            );
        }

        return $this->withCandidateKey(
            $type,
            $facets,
            $additionalIds,
            function (string $key) use (
                $type,
                $after,
                $limit,
            ): array {
                return $this->sourcePage($type, $key, $after, $limit);
            },
            $search,
        );
    }

    /**
     * Page a bounded additional ID set using retained source scores only.
     * Used for unfiltered reserved-lead membership under active source churn.
     *
     * @param  array<string, true>  $additionalIds
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    private function pageAdditionalIdsFromSource(
        RetainedJobType $type,
        array $additionalIds,
        ?RetainedJobPosition $after,
        int $limit,
    ): array {
        if ($limit <= 0 || $additionalIds === []) {
            return [
                'ids' => [],
                'total' => 0,
                'currentOffset' => $after === null ? 0 : $after->offset,
                'next' => null,
            ];
        }

        $scores = $this->scoresForRetainedIds(
            $type,
            array_keys($additionalIds),
        );
        $reverse = ! $type->newestFirst();
        $pairs = [];

        foreach ($scores as $id => $score) {
            $pairs[] = ['id' => $id, 'score' => $score];
        }

        usort(
            $pairs,
            static function (array $left, array $right) use ($reverse): int {
                $scoreComparison = $left['score'] <=> $right['score'];

                if ($scoreComparison !== 0) {
                    return $reverse ? -$scoreComparison : $scoreComparison;
                }

                $idComparison = $left['id'] <=> $right['id'];

                return $reverse ? -$idComparison : $idComparison;
            },
        );

        if ($after !== null && $after->score !== null) {
            $pairs = array_values(array_filter(
                $pairs,
                static function (array $pair) use ($after, $reverse): bool {
                    $scoreComparison = $pair['score'] <=> $after->score;

                    if ($scoreComparison !== 0) {
                        return $reverse
                            ? $scoreComparison < 0
                            : $scoreComparison > 0;
                    }

                    $idComparison = $pair['id'] <=> $after->id;

                    return $reverse
                        ? $idComparison < 0
                        : $idComparison > 0;
                },
            ));
        }

        $entries = [];

        foreach ($pairs as $pair) {
            $entries[$pair['id']] = $pair['score'];
        }

        return $this->scoredPageResult(
            $entries,
            count($scores),
            $after,
            $limit,
        );
    }

    /**
     * @param  array<string, string>  $facets
     * @param  array<int, string>  $temporaryKeys
     * @return array{
     *     ids: array<int, string>,
     *     scores: array<string, float>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    private function pageScoredIdsFromPublished(
        RetainedJobType $type,
        array $facets,
        string $scoredKey,
        ?RetainedJobPosition $after,
        int $limit,
        array &$temporaryKeys,
    ): array {
        $publishedState = $this->publishedState($type);

        if ($publishedState === null) {
            throw new RetainedJobIndexWarming;
        }

        $generation = $this->generationFromPublishedState($publishedState);
        $keys = [$scoredKey];

        foreach ($facets as $dimension => $value) {
            $keys[] = $this->facetKeyForGeneration(
                $type,
                $generation,
                $dimension,
                $value,
            );
        }

        $candidateKey = $this->temporaryKey('scored-published');
        $temporaryKeys[] = $candidateKey;
        $this->transaction(function (mixed $transaction) use (
            $candidateKey,
            $keys,
        ): void {
            $this->intersect(
                $transaction,
                $candidateKey,
                $keys,
                [1, ...array_fill(0, count($keys) - 1, 0)],
            );
            $transaction->expire(
                $candidateKey,
                self::TEMPORARY_KEY_TTL_SECONDS,
            );
        });

        if ($this->publishedState($type) !== $publishedState) {
            throw new RetainedJobIndexWarming;
        }

        $page = $this->sourcePage($type, $candidateKey, $after, $limit);

        return [
            'ids' => $page['ids'],
            'scores' => $this->scoresForIds($candidateKey, $page['ids']),
            'total' => $page['total'],
            'currentOffset' => $page['currentOffset'],
            'next' => $page['next'],
        ];
    }

    /**
     * @param  array<string, string>  $facets
     * @param  array<int, string>  $temporaryKeys
     * @return array{
     *     ids: array<int, string>,
     *     scores: array<string, float>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    private function pageScoredIdsFromLive(
        RetainedJobType $type,
        array $facets,
        string $scoredKey,
        ?RetainedJobPosition $after,
        int $limit,
        string $search,
        array &$temporaryKeys,
    ): array {
        return $this->withCandidateKey(
            $type,
            $facets,
            null,
            function (string $candidateKey) use (
                $type,
                $scoredKey,
                $after,
                $limit,
                &$temporaryKeys,
            ): array {
                // Intersect the small delayed-score set with the filtered candidate
                // so only delayed members pay for the filter path.
                $filteredKey = $this->temporaryKey('scored-filtered');
                $temporaryKeys[] = $filteredKey;
                $this->transaction(function (mixed $transaction) use (
                    $filteredKey,
                    $scoredKey,
                    $candidateKey,
                ): void {
                    $this->intersect(
                        $transaction,
                        $filteredKey,
                        [$scoredKey, $candidateKey],
                        [1, 0],
                    );
                    $transaction->expire(
                        $filteredKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                });

                $page = $this->sourcePage($type, $filteredKey, $after, $limit);

                return [
                    'ids' => $page['ids'],
                    'scores' => $this->scoresForIds($filteredKey, $page['ids']),
                    'total' => $page['total'],
                    'currentOffset' => $page['currentOffset'],
                    'next' => $page['next'],
                ];
            },
            $search,
        );
    }

    /**
     * Resolve zset scores for IDs in SOURCE_CHUNK_SIZE pipeline batches so a
     * large delayed backlog never becomes one unbounded Redis payload.
     *
     * @param  array<int, string>  $ids
     * @return array<string, float>
     */
    private function scoresForIds(string $key, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $normalized = [];

        foreach (array_chunk($ids, self::SOURCE_CHUNK_SIZE) as $chunk) {
            $scores = $this->pipeline(function (mixed $pipeline) use (
                $chunk,
                $key,
            ): void {
                foreach ($chunk as $id) {
                    $pipeline->zscore($key, $id);
                }
            });

            foreach ($chunk as $offset => $id) {
                $score = $scores[$offset] ?? false;

                if (is_numeric($score)) {
                    $normalized[$id] = (float) $score;
                }
            }
        }

        return $normalized;
    }

    /**
     * Resolve retained source scores for a bounded ID list (page-sized).
     *
     * @param  array<int, string>  $ids
     * @return array<string, float>
     */
    public function scoresForRetainedIds(
        RetainedJobType $type,
        array $ids,
    ): array {
        return $this->scoresForIds($type->sourceKey(), $ids);
    }

    /**
     * Keep only scored IDs still present in Horizon's retained source.
     * Work is proportional to the scored set (delayed backlog), not total retained;
     * presence checks run in SOURCE_CHUNK_SIZE pipelines.
     *
     * @param  array<string, float>  $scores
     * @param  array<string, true>  $excludeIds
     * @return array<string, float>
     */
    private function retainedScoredIds(
        RetainedJobType $type,
        array $scores,
        array $excludeIds,
    ): array {
        if ($scores === []) {
            return [];
        }

        $ids = [];

        foreach (array_keys($scores) as $id) {
            if (! isset($excludeIds[$id])) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $presence = $this->scoresForIds($type->sourceKey(), $ids);
        $retained = [];

        foreach ($presence as $id => $_sourceScore) {
            if (isset($scores[$id])) {
                $retained[$id] = $scores[$id];
            }
        }

        return $retained;
    }

    /**
     * Read a page from the last completed projection without inspecting or
     * reconciling Horizon's retained source.
     *
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    public function pageIdsFromPublishedIndex(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds,
        ?RetainedJobPosition $after,
        int $limit,
    ): array {
        return $this->withPublishedCandidateKey(
            $type,
            $facets,
            $additionalIds,
            fn (string $key): array => $this->sourcePage(
                $type,
                $key,
                $after,
                $limit,
            ),
        );
    }

    /**
     * Page candidate IDs while skipping a bounded exclusion set.
     *
     * Operates inside one candidate-key lifetime: each raw sourcePage asks only for the
     * remaining included rows still needed, filters exclusions, and advances with the exact
     * RetainedJobPosition returned by sourcePage.
     *
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     * @param  array<string, true>  $excludeIds
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    public function pageIdsExcluding(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds,
        array $excludeIds,
        ?RetainedJobPosition $after,
        int $limit,
        ?string $search = null,
    ): array {
        if ($excludeIds === []) {
            return $this->pageIds(
                $type,
                $facets,
                $additionalIds,
                $after,
                $limit,
                $search,
            );
        }

        $search = trim($search ?? '');

        if ($facets === [] && $additionalIds === null && $search === '') {
            return $this->sourcePageExcluding(
                $type,
                $type->sourceKey(),
                $excludeIds,
                $after,
                $limit,
            );
        }

        return $this->withCandidateKey(
            $type,
            $facets,
            $additionalIds,
            function (string $key) use (
                $type,
                $excludeIds,
                $after,
                $limit,
            ): array {
                return $this->sourcePageExcluding(
                    $type,
                    $key,
                    $excludeIds,
                    $after,
                    $limit,
                );
            },
            $search,
        );
    }

    /**
     * Published-index variant of pageIdsExcluding.
     *
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     * @param  array<string, true>  $excludeIds
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    public function pageIdsFromPublishedIndexExcluding(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds,
        array $excludeIds,
        ?RetainedJobPosition $after,
        int $limit,
    ): array {
        if ($excludeIds === []) {
            return $this->pageIdsFromPublishedIndex(
                $type,
                $facets,
                $additionalIds,
                $after,
                $limit,
            );
        }

        return $this->withPublishedCandidateKey(
            $type,
            $facets,
            $additionalIds,
            function (string $key) use (
                $type,
                $excludeIds,
                $after,
                $limit,
            ): array {
                return $this->sourcePageExcluding(
                    $type,
                    $key,
                    $excludeIds,
                    $after,
                    $limit,
                );
            },
        );
    }

    /**
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     */
    public function count(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds = null,
        ?float $since = null,
    ): int {
        if ($facets === [] && $additionalIds === null) {
            return $since === null
                ? $this->sortedSetCount($type->sourceKey())
                : $this->sortedSetCountSince(
                    $type->sourceKey(),
                    $since,
                );
        }

        return $this->withCandidateKey(
            $type,
            $facets,
            $additionalIds,
            fn (string $key): int => $since === null
                ? $this->sortedSetCount($key)
                : $this->sortedSetCountSince($key, $since),
        );
    }

    /**
     * @param  array<string, string>  $facets
     * @return array{total: int, hour: int, day: int}
     */
    public function periodCounts(
        RetainedJobType $type,
        array $facets,
        float $hourCutoff,
        float $dayCutoff,
    ): array {
        if ($facets === []) {
            return [
                'total' => $this->sortedSetCount($type->sourceKey()),
                'hour' => $this->sortedSetCountSince(
                    $type->sourceKey(),
                    $hourCutoff,
                ),
                'day' => $this->sortedSetCountSince(
                    $type->sourceKey(),
                    $dayCutoff,
                ),
            ];
        }

        return $this->withCandidateKey(
            $type,
            $facets,
            null,
            fn (string $key): array => [
                'total' => $this->sortedSetCount($key),
                'hour' => $this->sortedSetCountSince($key, $hourCutoff),
                'day' => $this->sortedSetCountSince($key, $dayCutoff),
            ],
        );
    }

    /**
     * Read rolling counts from the last completed projection without
     * inspecting or reconciling Horizon's retained source.
     *
     * @param  array<string, string>  $facets
     * @return array{total: int, hour: int, day: int}
     */
    public function periodCountsFromPublishedIndex(
        RetainedJobType $type,
        array $facets,
        float $hourCutoff,
        float $dayCutoff,
    ): array {
        return $this->withPublishedCandidateKey(
            $type,
            $facets,
            null,
            fn (string $key): array => [
                'total' => $this->sortedSetCount($key),
                'hour' => $this->sortedSetCountSince($key, $hourCutoff),
                'day' => $this->sortedSetCountSince($key, $dayCutoff),
            ],
        );
    }

    /** @return array<int, string> */
    public function catalogValues(
        RetainedJobType $type,
        string $dimension,
    ): array {
        return $this->catalogValuesFor(
            $type,
            [$dimension],
        )[$dimension];
    }

    /**
     * Read catalog members from the last published generation without live
     * source reconciliation. Used for pending target discovery under churn.
     *
     * @return array<int, string>
     */
    public function catalogValuesFromPublishedIndex(
        RetainedJobType $type,
        string $dimension,
    ): array {
        $publishedState = $this->publishedState($type);

        if ($publishedState === null) {
            throw new RetainedJobIndexWarming;
        }

        $generation = $this->generationFromPublishedState($publishedState);
        $values = $this->connection()->smembers(
            $this->catalogKeyForGeneration($type, $generation, $dimension),
        );

        if ($this->publishedState($type) !== $publishedState) {
            throw new RetainedJobIndexWarming;
        }

        return is_array($values)
            ? array_values(array_filter($values, is_string(...)))
            : [];
    }

    /**
     * Page a temporary scored ID set proportional only to the provided scores.
     * Optional facets/search intersect the small scored set (not the full source).
     *
     * @param  array<string, string>  $facets
     * @param  array<string, float>  $scores
     * @param  array<string, true>  $excludeIds
     * @return array{
     *     ids: array<int, string>,
     *     scores: array<string, float>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    public function pageScoredIds(
        RetainedJobType $type,
        array $facets,
        array $scores,
        array $excludeIds,
        ?RetainedJobPosition $after,
        int $limit,
        ?string $search = null,
        bool $published = false,
    ): array {
        if ($scores === [] || $limit <= 0) {
            return [
                'ids' => [],
                'scores' => [],
                'total' => 0,
                'currentOffset' => $after === null ? 0 : $after->offset,
                'next' => null,
            ];
        }

        // Drop queue-delayed payloads Horizon no longer retains. Bounded by the
        // delayed backlog via pipelined source ZSCORE — no candidate ZINTER/ZUNION.
        $scores = $this->retainedScoredIds($type, $scores, $excludeIds);

        if ($scores === []) {
            return [
                'ids' => [],
                'scores' => [],
                'total' => 0,
                'currentOffset' => $after === null ? 0 : $after->offset,
                'next' => null,
            ];
        }

        $search = trim($search ?? '');
        $temporaryKeys = [];

        try {
            $scoredKey = $this->temporaryKey('scored-ids');
            $temporaryKeys[] = $scoredKey;

            foreach (
                array_chunk($scores, self::SOURCE_CHUNK_SIZE, true) as $chunk
            ) {
                $this->transaction(function (mixed $transaction) use (
                    $chunk,
                    $scoredKey,
                ): void {
                    foreach ($chunk as $id => $score) {
                        $transaction->zadd($scoredKey, $score, $id);
                    }

                    $transaction->expire(
                        $scoredKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                });
            }

            if ($facets === [] && $search === '') {
                $page = $this->sourcePage($type, $scoredKey, $after, $limit);

                return [
                    'ids' => $page['ids'],
                    'scores' => $this->scoresForIds($scoredKey, $page['ids']),
                    'total' => $page['total'],
                    'currentOffset' => $page['currentOffset'],
                    'next' => $page['next'],
                ];
            }

            if ($published) {
                return $this->pageScoredIdsFromPublished(
                    $type,
                    $facets,
                    $scoredKey,
                    $after,
                    $limit,
                    $temporaryKeys,
                );
            }

            return $this->pageScoredIdsFromLive(
                $type,
                $facets,
                $scoredKey,
                $after,
                $limit,
                $search,
                $temporaryKeys,
            );
        } finally {
            foreach ($temporaryKeys as $key) {
                $this->deleteQuietly($key);
            }
        }
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<string, array<int, string>>
     */
    public function catalogValuesFor(
        RetainedJobType $type,
        array $dimensions,
    ): array {
        $dimensions = array_values(array_unique($dimensions));

        if ($dimensions === []) {
            return [];
        }

        $requiredCatalogPartitions = array_values(array_filter(
            $dimensions,
            $this->catalogDimensionIsSingleValued(...),
        ));

        for (
            $attempt = 0;
            $attempt < self::PERSISTENT_READ_SYNCHRONIZATION_ATTEMPTS;
            $attempt++
        ) {
            $temporaryKeys = [];

            try {
                $retainedSourceSnapshot = $this->synchronizedSourceSnapshot(
                    $type,
                    false,
                    [],
                    [],
                    $temporaryKeys,
                );
                $revision = $this->synchronizationRevision($type);

                if ($this->synchronizationIsActive($type)) {
                    continue;
                }

                $sourceSnapshot = $this->inspectableSourceSnapshot(
                    $type,
                    $retainedSourceSnapshot,
                    $temporaryKeys,
                );

                if ($sourceSnapshot === null) {
                    continue;
                }

                $available = [];

                foreach ($dimensions as $dimension) {
                    $available[$dimension] = $this->availableCatalogValues(
                        $type,
                        $dimension,
                        $sourceSnapshot,
                    );
                }

                if (
                    $this->synchronizationIsActive($type)
                    || $this->synchronizationRevision($type) !== $revision
                ) {
                    continue;
                }

                if ($requiredCatalogPartitions !== []) {
                    $this->synchronizeSnapshot(
                        $type,
                        $retainedSourceSnapshot,
                        false,
                        [],
                        $requiredCatalogPartitions,
                    );

                    if (
                        $this->synchronizationIsActive($type)
                        || $this->synchronizationRevision($type) !== $revision
                    ) {
                        continue;
                    }
                }

            } finally {
                foreach ($temporaryKeys as $key) {
                    $this->deleteQuietly($key);
                }
            }

            return $available;
        }

        throw new RuntimeException(
            'The retained job facet catalogs could not be read consistently.',
        );
    }

    public function metadataKey(): string
    {
        return $this->key('metadata');
    }

    /** @phpstan-impure */
    public function publishedRevision(
        RetainedJobType $type,
    ): ?string {
        return $this->publishedState($type);
    }

    public function projectionKey(RetainedJobType $type): string
    {
        return $this->projectionKeyForGeneration(
            $type,
            $this->currentGeneration($type),
        );
    }

    public function unresolvedCount(RetainedJobType $type): int
    {
        $activeKey = $this->temporaryKey('active-unresolved');

        try {
            $this->transaction(function (mixed $transaction) use (
                $activeKey,
                $type,
            ): void {
                $this->intersect(
                    $transaction,
                    $activeKey,
                    [
                        $type->sourceKey(),
                        $this->unresolvedKey($type),
                    ],
                    [1, 0],
                );
                $transaction->expire(
                    $activeKey,
                    self::TEMPORARY_KEY_TTL_SECONDS,
                );
            });

            return $this->sortedSetCount($activeKey);
        } finally {
            $this->deleteQuietly($activeKey);
        }
    }

    private function unresolvedKey(RetainedJobType $type): string
    {
        return $this->unresolvedKeyForGeneration(
            $type,
            $this->currentGeneration($type),
        );
    }

    private function storedUnresolvedCount(RetainedJobType $type): int
    {
        return $this->sortedSetCount($this->unresolvedKey($type));
    }

    public function facetKey(
        RetainedJobType $type,
        string $dimension,
        string $value,
    ): string {
        return $this->facetKeyForGeneration(
            $type,
            $this->currentGeneration($type),
            $dimension,
            $value,
        );
    }

    public function catalogKey(
        RetainedJobType $type,
        string $dimension,
    ): string {
        return $this->catalogKeyForGeneration(
            $type,
            $this->currentGeneration($type),
            $dimension,
        );
    }

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     */
    private function indexIsConsistent(
        RetainedJobType $type,
        array $requiredFacets,
        array $requiredCatalogPartitions,
        string $sourceKey,
        ?Closure $heartbeat = null,
    ): bool {
        $heartbeat ??= fn () => $this->renewSourceSnapshot($sourceKey);
        $heartbeat();
        $coverageGapKey = $this->temporaryKey('coverage-gap');

        try {
            $coverageIsExact = $this->difference(
                $coverageGapKey,
                $sourceKey,
                $this->projectionKey($type),
                $this->unresolvedKey($type),
            ) === 0;
        } finally {
            $this->deleteQuietly($coverageGapKey);
        }

        if (
            ! $coverageIsExact
            || $this->sortedSetCount($this->projectionKey($type))
                + $this->storedUnresolvedCount($type)
                !== $this->sortedSetCount($sourceKey)
        ) {
            return false;
        }

        return $this->indexStructureIsConsistent(
            $type,
            $requiredFacets,
            $requiredCatalogPartitions,
            $heartbeat,
        );
    }

    /**
     * @param  array<string, string>  $requiredFacets
     * @param  array<int, string>  $requiredCatalogPartitions
     */
    private function indexStructureIsConsistent(
        RetainedJobType $type,
        array $requiredFacets,
        array $requiredCatalogPartitions = [],
        ?Closure $heartbeat = null,
    ): bool {
        $heartbeat?->__invoke();
        $projectionCount = $this->sortedSetCount(
            $this->projectionKey($type),
        );

        if ($projectionCount > 0) {
            foreach ($this->requiredCatalogDimensions($type) as $dimension) {
                $heartbeat?->__invoke();

                if ($this->setCount($this->catalogKey($type, $dimension)) === 0) {
                    return false;
                }
            }
        }

        foreach (
            array_values(array_unique($requiredCatalogPartitions)) as $dimension
        ) {
            $heartbeat?->__invoke();

            if (
                ! $this->catalogDimensionIsSingleValued($dimension)
                || ! $this->catalogPartitionIsExact(
                    $type,
                    $dimension,
                    $projectionCount,
                    $heartbeat,
                )
            ) {
                return false;
            }
        }

        foreach ($requiredFacets as $dimension => $value) {
            $heartbeat?->__invoke();
            $facetHasMembers = $this->sortedSetCount(
                $this->facetKey($type, $dimension, $value),
            ) > 0;
            $catalogHasMember = $this->setContains(
                $this->catalogKey($type, $dimension),
                $value,
            );

            if ($facetHasMembers !== $catalogHasMember) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string> $requiredFacets */
    private function rebuildTypeIndex(
        RetainedJobType $type,
        string $lockToken,
        array $requiredFacets,
        string $sourceKey,
    ): void {
        $this->deleteIndexKeys($type, $lockToken, $sourceKey, [
            $this->projectionKey($type),
            $this->unresolvedKey($type),
        ]);

        foreach (self::FACET_DIMENSIONS as $dimension) {
            foreach ($this->catalogMemberChunks($type, $dimension) as $values) {
                $this->deleteIndexKeys(
                    $type,
                    $lockToken,
                    $sourceKey,
                    array_map(
                        fn (string $value): string => $this->facetKey(
                            $type,
                            $dimension,
                            $value,
                        ),
                        $values,
                    ),
                );
            }

            $this->deleteIndexKeys($type, $lockToken, $sourceKey, [
                $this->catalogKey($type, $dimension),
            ]);
        }

        $this->deleteIndexKeys(
            $type,
            $lockToken,
            $sourceKey,
            array_map(
                fn (string $value, string $dimension): string => $this->facetKey(
                    $type,
                    $dimension,
                    $value,
                ),
                array_values($requiredFacets),
                array_keys($requiredFacets),
            ),
        );

        $this->addMissingJobs($type, $lockToken, $sourceKey);
        $this->renewSynchronizationState($type, $lockToken, $sourceKey);
    }

    /** @param array<int, string> $keys */
    private function deleteIndexKeys(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
        array $keys,
    ): void {
        foreach (
            array_chunk(
                array_values(array_unique($keys)),
                self::CATALOG_SCAN_CHUNK_SIZE,
            ) as $chunk
        ) {
            $this->renewSynchronizationState(
                $type,
                $lockToken,
                $sourceKey,
            );
            $this->connection()->del(...$chunk);
        }
    }

    /** @return iterable<int, array<int, string>> */
    private function catalogMemberChunks(
        RetainedJobType $type,
        string $dimension,
    ): iterable {
        yield from $this->catalogMemberChunksForGeneration(
            $type,
            $this->currentGeneration($type),
            $dimension,
        );
    }

    /** @return iterable<int, array<int, string>> */
    private function catalogMemberChunksForGeneration(
        RetainedJobType $type,
        string $generation,
        string $dimension,
    ): iterable {
        $cursor = $this->connection() instanceof PhpRedisConnection
            ? null
            : 0;

        do {
            $result = $this->scanSet(
                $this->catalogKeyForGeneration(
                    $type,
                    $generation,
                    $dimension,
                ),
                $cursor,
            );

            if ($result === false) {
                return;
            }

            if (
                ! is_array($result)
                || ! isset($result[0], $result[1])
                || ! is_array($result[1])
            ) {
                throw new RuntimeException(
                    'The retained job facet catalog could not be scanned.',
                );
            }

            $cursor = $result[0];
            $members = array_values(array_filter(
                $result[1],
                is_string(...),
            ));

            foreach (
                array_chunk($members, self::CATALOG_SCAN_CHUNK_SIZE) as $chunk
            ) {
                yield $chunk;
            }
        } while ((string) $cursor !== '0');
    }

    private function scanSet(string $key, int|string|null $cursor): mixed
    {
        $connection = $this->connection();

        return $connection instanceof PhpRedisConnection
            ? $connection->sscan(
                $key,
                $cursor,
                ['count' => self::CATALOG_SCAN_CHUNK_SIZE],
            )
            : $connection->client()->sscan(
                $key,
                $cursor,
                ['count' => self::CATALOG_SCAN_CHUNK_SIZE],
            );
    }

    private function materializeSourceSnapshot(
        RetainedJobType $type,
    ): string {
        $sourceSnapshotKey = $this->temporaryKey(
            'retained-source-snapshot',
        );

        $this->transaction(function (mixed $transaction) use (
            $sourceSnapshotKey,
            $type,
        ): void {
            $this->intersect(
                $transaction,
                $sourceSnapshotKey,
                [$type->sourceKey()],
                [1],
            );
            $transaction->expire(
                $sourceSnapshotKey,
                self::SYNCHRONIZATION_LOCK_SECONDS,
            );
        });
        $this->sourceSnapshotCounts[$sourceSnapshotKey] = $this->sortedSetCount(
            $sourceSnapshotKey,
        );

        return $sourceSnapshotKey;
    }

    private function refreshSourceSnapshot(
        RetainedJobType $type,
        string $sourceSnapshotKey,
        ?Closure $heartbeat = null,
    ): void {
        $heartbeat ??= fn () => $this->renewSourceSnapshot(
            $sourceSnapshotKey,
        );
        $additionsKey = $this->temporaryKey(
            'retained-source-additions',
        );
        $removalsKey = $this->temporaryKey(
            'retained-source-removals',
        );

        try {
            $this->transaction(function (mixed $transaction) use (
                $additionsKey,
                $removalsKey,
                $sourceSnapshotKey,
                $type,
            ): void {
                $transaction->zdiffstore($additionsKey, [
                    $type->sourceKey(),
                    $sourceSnapshotKey,
                ]);
                $transaction->zdiffstore($removalsKey, [
                    $sourceSnapshotKey,
                    $type->sourceKey(),
                ]);

                foreach ([$additionsKey, $removalsKey] as $key) {
                    $transaction->expire(
                        $key,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                }
            });
            $heartbeat();

            while (true) {
                $scores = $this->sortedSetScores(
                    $additionsKey,
                    0,
                    self::SOURCE_CHUNK_SIZE - 1,
                );

                if ($scores === []) {
                    break;
                }

                $heartbeat();
                $this->transaction(function (mixed $transaction) use (
                    $additionsKey,
                    $removalsKey,
                    $scores,
                    $sourceSnapshotKey,
                ): void {
                    foreach ($scores as $id => $score) {
                        $transaction->zadd(
                            $sourceSnapshotKey,
                            $score,
                            $id,
                        );
                    }

                    $transaction->zrem(
                        $additionsKey,
                        ...array_keys($scores),
                    );
                    $transaction->expire(
                        $additionsKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                    $transaction->expire(
                        $removalsKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                    $transaction->expire(
                        $sourceSnapshotKey,
                        self::SYNCHRONIZATION_LOCK_SECONDS,
                    );
                });
            }

            while (true) {
                $ids = $this->connection()->zrange(
                    $removalsKey,
                    0,
                    self::SOURCE_CHUNK_SIZE - 1,
                );
                $ids = is_array($ids)
                    ? array_values(array_filter($ids, is_string(...)))
                    : [];

                if ($ids === []) {
                    break;
                }

                $heartbeat();
                $this->transaction(function (mixed $transaction) use (
                    $additionsKey,
                    $ids,
                    $removalsKey,
                    $sourceSnapshotKey,
                ): void {
                    $transaction->zrem($sourceSnapshotKey, ...$ids);
                    $transaction->zrem($removalsKey, ...$ids);
                    $transaction->expire(
                        $additionsKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                    $transaction->expire(
                        $removalsKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                    $transaction->expire(
                        $sourceSnapshotKey,
                        self::SYNCHRONIZATION_LOCK_SECONDS,
                    );
                });
            }

            $this->sourceSnapshotCounts[$sourceSnapshotKey] =
                $this->sortedSetCount($sourceSnapshotKey);
            $this->renewSourceSnapshot($sourceSnapshotKey);
        } finally {
            $this->deleteQuietly($additionsKey);
            $this->deleteQuietly($removalsKey);
        }
    }

    /**
     * Materialize the inspectable retained source and its coverage proof in
     * one Redis transaction so later source churn cannot change this read.
     *
     * @param  array<int, string>  $temporaryKeys
     */
    private function inspectableSourceSnapshot(
        RetainedJobType $type,
        string $retainedSourceKey,
        array &$temporaryKeys,
    ): ?string {
        $sourceSnapshotKey = $this->temporaryKey('source-snapshot');
        $coverageGapKey = $this->temporaryKey('coverage-gap');
        $temporaryKeys[] = $sourceSnapshotKey;
        $temporaryKeys[] = $coverageGapKey;

        $this->transaction(function (mixed $transaction) use (
            $coverageGapKey,
            $retainedSourceKey,
            $sourceSnapshotKey,
            $type,
        ): void {
            $this->intersect(
                $transaction,
                $sourceSnapshotKey,
                [
                    $retainedSourceKey,
                    $this->projectionKey($type),
                ],
                [1, 0],
            );
            $transaction->zdiffstore($coverageGapKey, [
                $retainedSourceKey,
                $this->projectionKey($type),
                $this->unresolvedKey($type),
            ]);

            foreach ([$sourceSnapshotKey, $coverageGapKey] as $key) {
                $transaction->expire(
                    $key,
                    self::TEMPORARY_KEY_TTL_SECONDS,
                );
            }
        });

        if ($this->sortedSetCount($coverageGapKey) !== 0) {
            return null;
        }

        $this->sourceSnapshotCounts[$sourceSnapshotKey] =
            $this->sortedSetCount($sourceSnapshotKey);

        return $sourceSnapshotKey;
    }

    /**
     * @return array<int, string>
     */
    private function availableCatalogValues(
        RetainedJobType $type,
        string $dimension,
        string $sourceSnapshotKey,
    ): array {
        $available = [];

        foreach (
            $this->catalogMemberChunks($type, $dimension) as $chunk
        ) {
            $this->renewSourceSnapshot($sourceSnapshotKey);
            $counts = $this->facetCountsWithinSnapshot(
                $type,
                $dimension,
                $chunk,
                $sourceSnapshotKey,
            );

            foreach ($chunk as $offset => $value) {
                if (($counts[$offset] ?? 0) === 0) {
                    continue;
                }

                $available[] = $value;
            }
        }

        return $available;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, int>
     */
    private function facetCountsWithinSnapshot(
        RetainedJobType $type,
        string $dimension,
        array $values,
        string $sourceSnapshotKey,
    ): array {
        $membershipKeys = array_map(
            fn (string $value): string => $this->temporaryKey(
                'catalog-membership',
            ),
            $values,
        );

        try {
            $this->transaction(function (mixed $transaction) use (
                $dimension,
                $membershipKeys,
                $sourceSnapshotKey,
                $type,
                $values,
            ): void {
                foreach ($values as $offset => $value) {
                    $membershipKey = $membershipKeys[$offset];
                    $this->intersect(
                        $transaction,
                        $membershipKey,
                        [
                            $sourceSnapshotKey,
                            $this->facetKey($type, $dimension, $value),
                        ],
                        [1, 0],
                    );
                    $transaction->expire(
                        $membershipKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                }
            });

            $counts = $this->pipeline(
                function (mixed $pipeline) use ($membershipKeys): void {
                    foreach ($membershipKeys as $membershipKey) {
                        $pipeline->zcard($membershipKey);
                    }
                },
            );

            if (count($counts) !== count($values)) {
                throw new RuntimeException(
                    'The retained job facets could not be queried.',
                );
            }

            return array_map(
                static function (mixed $count): int {
                    if (! is_numeric($count)) {
                        throw new RuntimeException(
                            'The retained job facet catalog is invalid.',
                        );
                    }

                    return max(0, (int) $count);
                },
                $counts,
            );
        } finally {
            foreach ($membershipKeys as $membershipKey) {
                $this->deleteQuietly($membershipKey);
            }
        }
    }

    private function addMissingJobs(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
    ): int {
        $missingKey = $this->temporaryKey('missing');

        try {
            $missingCount = $this->difference(
                $missingKey,
                $sourceKey,
                $this->projectionKey($type),
                $this->unresolvedKey($type),
            );
            $start = 0;

            if ($missingCount > 0) {
                $this->renewWorkingSet(
                    $type,
                    $lockToken,
                    $missingKey,
                    $sourceKey,
                );
            }

            while (true) {
                $scores = $this->sortedSetScores(
                    $missingKey,
                    $start,
                    $start + self::SOURCE_CHUNK_SIZE - 1,
                );

                if ($scores === []) {
                    break;
                }

                $heartbeat = fn () => $this->renewWorkingSet(
                    $type,
                    $lockToken,
                    $missingKey,
                    $sourceKey,
                );
                $heartbeat();
                $this->hydrateAndStore($type, $scores, $heartbeat);

                if (count($scores) < self::SOURCE_CHUNK_SIZE) {
                    break;
                }

                $start += self::SOURCE_CHUNK_SIZE;
            }

            return $missingCount;
        } finally {
            $this->deleteQuietly($missingKey);
        }
    }

    /** @param array<string, float> $scores */
    private function hydrateAndStore(
        RetainedJobType $type,
        array $scores,
        Closure $heartbeat,
    ): void {
        $metadataById = $this->existingMetadata(array_keys($scores));
        $missingMetadata = array_values(array_diff(
            array_keys($scores),
            array_keys($metadataById),
        ));

        foreach (array_chunk(
            $missingMetadata,
            max(1, $this->hydrationChunkSize),
        ) as $chunk) {
            foreach ($this->jobs->getJobs($chunk) as $job) {
                if (! is_object($job)) {
                    continue;
                }

                $metadata = RetainedJobMetadata::fromJob($job);

                if ($metadata !== null) {
                    $metadataById[$metadata->id] = $metadata;
                }
            }

            $heartbeat();
        }

        $unresolved = [];

        foreach (array_keys($scores) as $id) {
            if (! isset($metadataById[$id])) {
                $score = $scores[$id];

                if (
                    -$score
                    <= microtime(true)
                        - self::UNRESOLVED_REFERENCE_GRACE_SECONDS
                ) {
                    $unresolved[$id] = $score;
                }

                unset($scores[$id]);
            }
        }

        foreach (array_chunk($unresolved, self::SOURCE_CHUNK_SIZE, true) as $chunk) {
            $this->transaction(function (mixed $transaction) use (
                $type,
                $chunk,
            ): void {
                foreach ($chunk as $id => $score) {
                    $transaction->zadd(
                        $this->unresolvedKey($type),
                        $score,
                        $id,
                    );
                }
            });
            $heartbeat();
        }

        foreach (array_chunk($scores, self::SOURCE_CHUNK_SIZE, true) as $chunk) {
            $this->transaction(function (mixed $transaction) use (
                $type,
                $chunk,
                $metadataById,
            ): void {
                foreach ($chunk as $id => $score) {
                    $metadata = $metadataById[$id] ?? null;

                    if (! $metadata instanceof RetainedJobMetadata) {
                        continue;
                    }

                    $transaction->hset(
                        $this->metadataKey(),
                        $id,
                        $metadata->encode(),
                    );

                    foreach ($this->facetValues($type, $metadata) as $dimension => $values) {
                        foreach ($values as $value) {
                            $transaction->zadd(
                                $this->facetKey($type, $dimension, $value),
                                $score,
                                $id,
                            );
                            $transaction->sadd(
                                $this->catalogKey($type, $dimension),
                                $value,
                            );
                        }
                    }

                    $transaction->zadd(
                        $this->projectionKey($type),
                        $score,
                        $id,
                    );
                    $transaction->zrem(
                        $this->unresolvedKey($type),
                        $id,
                    );
                }
            });
            $heartbeat();
        }
    }

    private function removeStaleJobs(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
    ): void {
        $staleKey = $this->temporaryKey('stale');

        try {
            $this->difference(
                $staleKey,
                $this->projectionKey($type),
                $sourceKey,
            );

            while (true) {
                $ids = $this->connection()->zrange(
                    $staleKey,
                    0,
                    self::SOURCE_CHUNK_SIZE - 1,
                );
                $ids = is_array($ids)
                    ? array_values(array_filter($ids, is_string(...)))
                    : [];

                if ($ids === []) {
                    break;
                }

                $this->renewWorkingSet(
                    $type,
                    $lockToken,
                    $staleKey,
                    $sourceKey,
                );
                $this->removeStaleChunk($type, $ids, $lockToken);
                $this->connection()->zrem($staleKey, ...$ids);
            }
        } finally {
            $this->deleteQuietly($staleKey);
        }
    }

    private function removeStaleUnresolvedReferences(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
    ): void {
        $staleKey = $this->temporaryKey('stale-unresolved');

        try {
            $this->difference(
                $staleKey,
                $this->unresolvedKey($type),
                $sourceKey,
            );

            while (true) {
                $ids = $this->connection()->zrange(
                    $staleKey,
                    0,
                    self::SOURCE_CHUNK_SIZE - 1,
                );
                $ids = is_array($ids)
                    ? array_values(array_filter($ids, is_string(...)))
                    : [];

                if ($ids === []) {
                    break;
                }

                $this->renewWorkingSet(
                    $type,
                    $lockToken,
                    $staleKey,
                    $sourceKey,
                );
                $this->connection()->zrem(
                    $this->unresolvedKey($type),
                    ...$ids,
                );
                $this->connection()->zrem($staleKey, ...$ids);
            }
        } finally {
            $this->deleteQuietly($staleKey);
        }
    }

    /** @param array<int, string> $ids */
    private function removeStaleChunk(
        RetainedJobType $type,
        array $ids,
        string $lockToken,
    ): void {
        $metadataById = $this->existingMetadata($ids);
        $idsWithoutMetadata = array_values(array_diff(
            $ids,
            array_keys($metadataById),
        ));
        $affectedFacets = [];

        $facetKeys = [];

        foreach ($metadataById as $metadata) {
            foreach ($this->facetValues($type, $metadata) as $dimension => $values) {
                foreach ($values as $value) {
                    $facetKey = $this->facetKey($type, $dimension, $value);
                    $facetKeys[$facetKey] = [$dimension, $value];
                }
            }
        }

        // Snapshot counts before MULTI so catalog cleanup only runs when this
        // removal emptied a live facet — not when the facet was already missing.
        $facetCountsBefore = [];

        if ($facetKeys !== []) {
            $keys = array_keys($facetKeys);
            $counts = $this->pipeline(function (mixed $pipeline) use ($keys): void {
                foreach ($keys as $facetKey) {
                    $pipeline->zcard($facetKey);
                }
            });

            foreach ($keys as $offset => $facetKey) {
                $facetCountsBefore[$facetKey] = is_numeric($counts[$offset] ?? null)
                    ? (int) $counts[$offset]
                    : 0;
            }
        }

        $this->transaction(function (mixed $transaction) use (
            $type,
            $ids,
            $metadataById,
            &$affectedFacets,
        ): void {
            foreach ($ids as $id) {
                $transaction->zrem($this->projectionKey($type), $id);
                $metadata = $metadataById[$id] ?? null;

                if (! $metadata instanceof RetainedJobMetadata) {
                    continue;
                }

                foreach ($this->facetValues($type, $metadata) as $dimension => $values) {
                    foreach ($values as $value) {
                        $facetKey = $this->facetKey($type, $dimension, $value);
                        $transaction->zrem($facetKey, $id);
                        $affectedFacets[$facetKey] = [$dimension, $value];
                    }
                }
            }
        });

        if ($idsWithoutMetadata !== []) {
            $this->removeUnknownFacetMemberships(
                $type,
                $idsWithoutMetadata,
                $lockToken,
            );
        }

        foreach ($affectedFacets as $facetKey => [$dimension, $value]) {
            if ($this->sortedSetCount($facetKey) !== 0) {
                continue;
            }

            if (($facetCountsBefore[$facetKey] ?? 0) === 0) {
                // Facet was already absent (e.g. external delete). Catalog may
                // still describe projection members; do not truncate it here.
                continue;
            }

            $this->connection()->srem(
                $this->catalogKey($type, $dimension),
                $value,
            );
            $this->connection()->del($facetKey);
        }

        $retained = $this->retainedByAnySource($ids);

        foreach ($ids as $id) {
            if (! ($retained[$id] ?? true)) {
                $this->connection()->hdel($this->metadataKey(), $id);
            }
        }
    }

    /** @param array<int, string> $ids */
    private function removeUnknownFacetMemberships(
        RetainedJobType $type,
        array $ids,
        string $lockToken,
    ): void {
        foreach (self::FACET_DIMENSIONS as $dimension) {
            foreach ($this->catalogMemberChunks($type, $dimension) as $values) {
                $this->renewSynchronizationLock($type, $lockToken);
                $facetKeys = array_map(
                    fn (string $value): string => $this->facetKey(
                        $type,
                        $dimension,
                        $value,
                    ),
                    $values,
                );
                $this->transaction(function (mixed $transaction) use (
                    $facetKeys,
                    $ids,
                ): void {
                    foreach ($facetKeys as $facetKey) {
                        $transaction->zrem($facetKey, ...$ids);
                    }
                });

                $counts = $this->pipeline(
                    function (mixed $pipeline) use ($facetKeys): void {
                        foreach ($facetKeys as $facetKey) {
                            $pipeline->zcard($facetKey);
                        }
                    },
                );

                if (count($counts) !== count($facetKeys)) {
                    throw new RuntimeException(
                        'The retained job facets could not be cleaned.',
                    );
                }

                $emptyFacets = [];

                foreach ($values as $offset => $value) {
                    $count = $counts[$offset] ?? null;

                    if (! is_numeric($count)) {
                        throw new RuntimeException(
                            'The retained job facet catalog is invalid.',
                        );
                    }

                    if ((int) $count === 0) {
                        $emptyFacets[$facetKeys[$offset]] = $value;
                    }
                }

                if ($emptyFacets === []) {
                    continue;
                }

                $this->renewSynchronizationLock($type, $lockToken);
                $this->transaction(function (mixed $transaction) use (
                    $dimension,
                    $emptyFacets,
                    $type,
                ): void {
                    foreach ($emptyFacets as $facetKey => $value) {
                        $transaction->srem(
                            $this->catalogKey($type, $dimension),
                            $value,
                        );
                        $transaction->del($facetKey);
                    }
                });
            }
        }
    }

    /**
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     *
     * @template TValue
     *
     * @param  Closure(string): TValue  $callback
     * @param  non-empty-string|string  $search
     * @return TValue
     */
    private function withCandidateKey(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds,
        Closure $callback,
        string $search = '',
    ): mixed {
        for (
            $attempt = 0;
            $attempt < self::CANDIDATE_SYNCHRONIZATION_ATTEMPTS;
            $attempt++
        ) {
            $temporaryKeys = [];

            try {
                $retainedSourceSnapshot = $this->synchronizedSourceSnapshot(
                    $type,
                    false,
                    $facets,
                    $search === '' ? [] : ['job'],
                    $temporaryKeys,
                );
                $revision = $this->synchronizationRevision($type);

                if ($this->synchronizationIsActive($type)) {
                    continue;
                }

                $sourceSnapshot = $this->inspectableSourceSnapshot(
                    $type,
                    $retainedSourceSnapshot,
                    $temporaryKeys,
                );

                if ($sourceSnapshot === null) {
                    continue;
                }

                $keys = [$sourceSnapshot];

                foreach ($facets as $dimension => $value) {
                    $keys[] = $this->facetKey(
                        $type,
                        $dimension,
                        $value,
                    );
                }

                if ($search !== '') {
                    $searchKey = $this->searchKey(
                        $type,
                        $search,
                        $temporaryKeys,
                        $sourceSnapshot,
                    );

                    if ($searchKey !== null) {
                        $keys[] = $searchKey;
                    }
                }

                if ($additionalIds !== null) {
                    $stateKey = $this->temporaryKey('state');
                    $temporaryKeys[] = $stateKey;

                    foreach (array_chunk(
                        array_keys($additionalIds),
                        self::SOURCE_CHUNK_SIZE,
                    ) as $chunk) {
                        $this->renewSourceSnapshot($sourceSnapshot);
                        $this->transaction(function (mixed $transaction) use (
                            $stateKey,
                            $chunk,
                        ): void {
                            foreach ($chunk as $id) {
                                $transaction->zadd($stateKey, 0, $id);
                            }

                            $transaction->expire(
                                $stateKey,
                                self::TEMPORARY_KEY_TTL_SECONDS,
                            );
                        });
                    }
                    $keys[] = $stateKey;
                }

                $candidateKey = $this->temporaryKey('query');
                $temporaryKeys[] = $candidateKey;
                $weights = [1, ...array_fill(0, count($keys) - 1, 0)];

                $this->renewSourceSnapshot($sourceSnapshot);
                $this->transaction(function (mixed $transaction) use (
                    $candidateKey,
                    $keys,
                    $weights,
                ): void {
                    $this->intersect(
                        $transaction,
                        $candidateKey,
                        $keys,
                        $weights,
                    );
                    $transaction->expire(
                        $candidateKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                });

                $result = $callback($candidateKey);

                if (
                    $this->synchronizationIsActive($type)
                    || $this->synchronizationRevision($type) !== $revision
                ) {
                    continue;
                }

                return $result;
            } finally {
                foreach ($temporaryKeys as $key) {
                    $this->deleteQuietly($key);
                }
            }
        }

        throw new RuntimeException(
            'The retained job index could not be synchronized.',
        );
    }

    /**
     * @param  array<string, string>  $facets
     * @param  array<string, true>|null  $additionalIds
     *
     * @template TValue
     *
     * @param  Closure(string): TValue  $callback
     * @return TValue
     */
    private function withPublishedCandidateKey(
        RetainedJobType $type,
        array $facets,
        ?array $additionalIds,
        Closure $callback,
    ): mixed {
        $publishedState = $this->publishedState($type);

        if ($publishedState === null) {
            throw new RetainedJobIndexWarming;
        }

        $generation = $this->generationFromPublishedState($publishedState);
        $temporaryKeys = [];

        try {
            $keys = array_map(
                fn (string $value, string $dimension): string => $this
                    ->facetKeyForGeneration(
                        $type,
                        $generation,
                        $dimension,
                        $value,
                    ),
                $facets,
                array_keys($facets),
            );

            if ($keys === []) {
                $keys[] = $this->projectionKeyForGeneration(
                    $type,
                    $generation,
                );
            }

            if ($additionalIds !== null) {
                $stateKey = $this->temporaryKey('published-state');
                $temporaryKeys[] = $stateKey;

                foreach (array_chunk(
                    array_keys($additionalIds),
                    self::SOURCE_CHUNK_SIZE,
                ) as $chunk) {
                    $this->transaction(function (mixed $transaction) use (
                        $stateKey,
                        $chunk,
                    ): void {
                        foreach ($chunk as $id) {
                            $transaction->zadd($stateKey, 0, $id);
                        }

                        $transaction->expire(
                            $stateKey,
                            self::TEMPORARY_KEY_TTL_SECONDS,
                        );
                    });
                }

                $keys[] = $stateKey;
            }

            if (count($keys) === 1) {
                $result = $callback($keys[0]);
            } else {
                $candidateKey = $this->temporaryKey('published-query');
                $temporaryKeys[] = $candidateKey;

                $this->transaction(function (mixed $transaction) use (
                    $candidateKey,
                    $keys,
                ): void {
                    $this->intersect(
                        $transaction,
                        $candidateKey,
                        $keys,
                        [1, ...array_fill(0, count($keys) - 1, 0)],
                    );
                    $transaction->expire(
                        $candidateKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                });

                $result = $callback($candidateKey);
            }

            if ($this->publishedState($type) !== $publishedState) {
                throw new RetainedJobIndexWarming;
            }

            return $result;
        } finally {
            foreach ($temporaryKeys as $key) {
                $this->deleteQuietly($key);
            }
        }
    }

    /**
     * @param  array<int, string>  $temporaryKeys
     */
    private function searchKey(
        RetainedJobType $type,
        string $search,
        array &$temporaryKeys,
        string $sourceSnapshotKey,
    ): ?string {
        $matchingJobNames = $this->matchingJobNames(
            $type,
            $search,
            $sourceSnapshotKey,
        );

        if ($matchingJobNames === null) {
            return null;
        }

        $keys = array_map(
            fn (string $jobName): string => $this->facetKey(
                $type,
                'job',
                $jobName,
            ),
            $matchingJobNames,
        );
        $idScore = $this->connection()->zscore(
            $sourceSnapshotKey,
            $search,
        );

        if (is_numeric($idScore)) {
            $idKey = $this->temporaryKey('search-id');
            $temporaryKeys[] = $idKey;
            $this->transaction(function (mixed $transaction) use (
                $idKey,
                $search,
            ): void {
                $transaction->zadd($idKey, 0, $search);
                $transaction->expire(
                    $idKey,
                    self::TEMPORARY_KEY_TTL_SECONDS,
                );
            });
            $keys[] = $idKey;
        }

        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            $emptyKey = $this->temporaryKey('search-empty');
            $temporaryKeys[] = $emptyKey;

            return $emptyKey;
        }

        if (count($keys) === 1) {
            return $keys[0];
        }

        return $this->unionKeysInChunks(
            $keys,
            $temporaryKeys,
            purpose: 'search',
        );
    }

    /**
     * @return array<int, string>|null
     */
    private function matchingJobNames(
        RetainedJobType $type,
        string $search,
        string $sourceSnapshotKey,
    ): ?array {
        $matchingJobNames = [];
        $sawNonMatchingJobName = false;
        $jobNames = $this->availableCatalogValues(
            $type,
            'job',
            $sourceSnapshotKey,
        );

        foreach ($jobNames as $jobName) {
            if (! Str::contains($jobName, $search, ignoreCase: true)) {
                $sawNonMatchingJobName = true;

                continue;
            }

            if (isset($matchingJobNames[$jobName])) {
                continue;
            }

            $matchingJobNames[$jobName] = true;
        }

        if ($jobNames !== [] && ! $sawNonMatchingJobName) {
            return null;
        }

        return array_keys($matchingJobNames);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, int>
     */
    private function facetCounts(
        RetainedJobType $type,
        string $dimension,
        array $values,
    ): array {
        $facetKeys = [];

        foreach ($values as $value) {
            $facetKeys[] = $this->facetKey($type, $dimension, $value);
        }

        $counts = $this->pipeline(function (mixed $pipeline) use ($facetKeys): void {
            foreach ($facetKeys as $facetKey) {
                $pipeline->zcard($facetKey);
            }
        });

        if (count($counts) !== count($values)) {
            throw new RuntimeException(
                'The retained job facets could not be queried.',
            );
        }

        $normalized = [];

        foreach ($counts as $count) {
            if (! is_numeric($count)) {
                throw new RuntimeException(
                    'The retained job facet catalog is invalid.',
                );
            }

            $normalized[] = max(0, (int) $count);
        }

        return $normalized;
    }

    private function catalogPartitionIsExact(
        RetainedJobType $type,
        string $dimension,
        int $projectionCount,
        ?Closure $heartbeat = null,
    ): bool {
        $heartbeat?->__invoke();
        $values = $this->catalogMembers($type, $dimension);

        if ($values === []) {
            return $projectionCount === 0;
        }

        $membershipCount = 0;
        $facetKeys = [];

        foreach (array_chunk($values, self::CATALOG_SCAN_CHUNK_SIZE) as $chunk) {
            $heartbeat?->__invoke();

            foreach ($this->facetCounts($type, $dimension, $chunk) as $count) {
                if ($count === 0) {
                    return false;
                }

                $membershipCount += $count;
            }

            foreach ($chunk as $value) {
                $facetKeys[] = $this->facetKey(
                    $type,
                    $dimension,
                    $value,
                );
            }
        }

        if ($membershipCount !== $projectionCount) {
            return false;
        }

        $temporaryKeys = [];

        try {
            $unionKey = $this->unionKeysInChunks(
                $facetKeys,
                $temporaryKeys,
                $heartbeat,
            );

            if ($unionKey === null) {
                return $projectionCount === 0;
            }

            $outsideProjectionKey = $this->temporaryKey(
                'catalog-outside-projection',
            );
            $missingFromCatalogKey = $this->temporaryKey(
                'catalog-missing-projection',
            );
            $temporaryKeys[] = $outsideProjectionKey;
            $temporaryKeys[] = $missingFromCatalogKey;
            $heartbeat?->__invoke();
            $outsideProjectionCount = $this->difference(
                $outsideProjectionKey,
                $unionKey,
                $this->projectionKey($type),
            );
            $heartbeat?->__invoke();
            $missingFromCatalogCount = $this->difference(
                $missingFromCatalogKey,
                $this->projectionKey($type),
                $unionKey,
            );

            return $outsideProjectionCount === 0
                && $missingFromCatalogCount === 0;
        } finally {
            foreach ($temporaryKeys as $key) {
                $this->deleteQuietly($key);
            }
        }
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $temporaryKeys
     */
    private function unionKeysInChunks(
        array $keys,
        array &$temporaryKeys,
        ?Closure $heartbeat = null,
        string $purpose = 'catalog-union',
    ): ?string {
        $keys = array_values(array_unique($keys));

        while (count($keys) > 1) {
            $heartbeat?->__invoke();
            $nextKeys = [];

            foreach (
                array_chunk($keys, self::CATALOG_SCAN_CHUNK_SIZE) as $chunk
            ) {
                $heartbeat?->__invoke();

                if (count($chunk) === 1) {
                    $nextKeys[] = $chunk[0];

                    continue;
                }

                $unionKey = $this->temporaryKey($purpose);
                $temporaryKeys[] = $unionKey;
                $this->transaction(function (mixed $transaction) use (
                    $unionKey,
                    $chunk,
                ): void {
                    $this->union($transaction, $unionKey, $chunk);
                    $transaction->expire(
                        $unionKey,
                        self::TEMPORARY_KEY_TTL_SECONDS,
                    );
                });
                $nextKeys[] = $unionKey;
            }

            $keys = $nextKeys;
        }

        return $keys[0] ?? null;
    }

    private function catalogDimensionIsSingleValued(
        string $dimension,
    ): bool {
        return in_array(
            $dimension,
            self::SINGLE_VALUED_CATALOG_DIMENSIONS,
            true,
        );
    }

    /**
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    private function sourcePage(
        RetainedJobType $type,
        string $key,
        ?RetainedJobPosition $after,
        int $limit,
    ): array {
        $entries = $this->pageEntries(
            $key,
            $after,
            $limit + 1,
            reverse: ! $type->newestFirst(),
        );

        return $this->scoredPageResult(
            $entries,
            $this->sortedSetCount($key),
            $after,
            $limit,
        );
    }

    /**
     * @param  array<string, true>  $excludeIds
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    private function sourcePageExcluding(
        RetainedJobType $type,
        string $key,
        array $excludeIds,
        ?RetainedJobPosition $after,
        int $limit,
    ): array {
        if ($limit <= 0) {
            return [
                'ids' => [],
                'total' => $this->sortedSetCount($key),
                'currentOffset' => $after === null ? 0 : $after->offset,
                'next' => null,
            ];
        }

        $collected = [];
        $cursor = $after;
        $currentOffset = $after === null ? 0 : $after->offset;
        $total = $this->sortedSetCount($key);
        // Each raw page asks only for the remaining included count; exclusions force additional
        // raw pages, but never more than one extra raw member per excluded id in the window.
        $maxRawPages = $limit + count($excludeIds) + 1;

        for ($rawPage = 0; $rawPage < $maxRawPages; $rawPage++) {
            $remaining = $limit - count($collected);

            if ($remaining <= 0) {
                break;
            }

            $page = $this->sourcePage($type, $key, $cursor, $remaining);

            if ($page['ids'] === []) {
                return [
                    'ids' => $collected,
                    'total' => $total,
                    'currentOffset' => $currentOffset,
                    'next' => null,
                ];
            }

            foreach ($page['ids'] as $id) {
                if (isset($excludeIds[$id])) {
                    continue;
                }

                $collected[] = $id;

                if (count($collected) === $limit) {
                    // Raw limit equals remaining needed, so filling requires zero exclusions on
                    // this raw page and the last raw id is the last included id. next is exact.
                    return [
                        'ids' => $collected,
                        'total' => $total,
                        'currentOffset' => $currentOffset,
                        'next' => $page['next'] instanceof RetainedJobPosition
                            ? $page['next']
                            : null,
                    ];
                }
            }

            if (! $page['next'] instanceof RetainedJobPosition) {
                return [
                    'ids' => $collected,
                    'total' => $total,
                    'currentOffset' => $currentOffset,
                    'next' => null,
                ];
            }

            $cursor = $page['next'];
        }

        return [
            'ids' => $collected,
            'total' => $total,
            'currentOffset' => $currentOffset,
            'next' => $cursor instanceof RetainedJobPosition ? $cursor : null,
        ];
    }

    /**
     * @param  array<string, float>  $entries
     * @return array{
     *     ids: array<int, string>,
     *     total: int,
     *     currentOffset: int,
     *     next: RetainedJobPosition|null
     * }
     */
    private function scoredPageResult(
        array $entries,
        int $total,
        ?RetainedJobPosition $after,
        int $limit,
    ): array {
        $hasMore = count($entries) > $limit;
        $pageEntries = array_slice($entries, 0, $limit, true);
        $ids = array_keys($pageEntries);
        $currentOffset = $after === null ? 0 : $after->offset;
        $lastId = $ids === [] ? null : $ids[array_key_last($ids)];

        return [
            'ids' => $ids,
            'total' => $total,
            'currentOffset' => $currentOffset,
            'next' => $hasMore && $lastId !== null
                ? new RetainedJobPosition(
                    score: $pageEntries[$lastId],
                    id: $lastId,
                    offset: $currentOffset + count($ids),
                )
                : null,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function pageEntries(
        string $key,
        ?RetainedJobPosition $after,
        int $limit,
        bool $reverse,
    ): array {
        if ($after === null) {
            return $this->sortedSetScores(
                $key,
                0,
                $limit - 1,
                $reverse,
            );
        }

        if ($after->score === null) {
            throw new RuntimeException(
                'The retained job cursor score is unavailable.',
            );
        }

        $lowerScoreCount = $this->sortedSetCountBetween(
            $key,
            '-inf',
            '('.$this->scoreLiteral($after->score),
        );
        $sameScoreCount = $this->sortedSetCountBetween(
            $key,
            $this->scoreLiteral($after->score),
            $this->scoreLiteral($after->score),
        );

        $sameScoreBoundary = $this->tieBoundary(
            $key,
            $lowerScoreCount,
            $sameScoreCount,
            $after->id,
            includeEqual: ! $reverse,
        );
        $start = $reverse
            ? $this->sortedSetCount($key)
                - ($lowerScoreCount + $sameScoreBoundary)
            : $lowerScoreCount + $sameScoreBoundary;

        return $this->sortedSetScores(
            $key,
            max(0, $start),
            max(0, $start) + $limit - 1,
            $reverse,
        );
    }

    private function tieBoundary(
        string $key,
        int $lowerScoreCount,
        int $sameScoreCount,
        string $cursorId,
        bool $includeEqual,
    ): int {
        $low = 0;
        $high = $sameScoreCount;

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $member = $this->connection()->zrange(
                $key,
                $lowerScoreCount + $middle,
                $lowerScoreCount + $middle,
            );
            $member = is_array($member) && is_string($member[0] ?? null)
                ? $member[0]
                : null;

            if ($member === null) {
                throw new RuntimeException(
                    'The retained job cursor boundary is unavailable.',
                );
            }

            $comparison = strcmp($member, $cursorId);
            $isBeforeBoundary = $includeEqual
                ? $comparison <= 0
                : $comparison < 0;

            if ($isBeforeBoundary) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<string, RetainedJobMetadata>
     */
    private function existingMetadata(array $ids): array
    {
        if (
            $ids === []
            || (int) $this->connection()->hlen($this->metadataKey()) === 0
        ) {
            return [];
        }

        $metadata = [];

        foreach (array_chunk($ids, self::SOURCE_CHUNK_SIZE) as $chunk) {
            $values = $this->connection()->hmget($this->metadataKey(), $chunk);
            $values = is_array($values) ? array_values($values) : [];

            foreach ($chunk as $offset => $id) {
                $value = RetainedJobMetadata::decode(
                    $id,
                    $values[$offset] ?? null,
                );

                if ($value !== null) {
                    $metadata[$id] = $value;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<string, bool>
     */
    private function retainedByAnySource(array $ids): array
    {
        $results = $this->pipeline(function (mixed $pipeline) use ($ids): void {
            foreach ($ids as $id) {
                foreach (RetainedJobType::cases() as $type) {
                    $pipeline->zscore($type->sourceKey(), $id);
                }
            }
        });

        if (count($results) !== count($ids) * count(RetainedJobType::cases())) {
            return array_fill_keys($ids, true);
        }

        $retained = [];
        $offset = 0;

        foreach ($ids as $id) {
            $retained[$id] = false;

            foreach (RetainedJobType::cases() as $_type) {
                if (is_numeric($results[$offset] ?? null)) {
                    $retained[$id] = true;
                }

                $offset++;
            }
        }

        return $retained;
    }

    /** @return array<int, string> */
    private function catalogMembers(
        RetainedJobType $type,
        string $dimension,
    ): array {
        $values = $this->connection()->smembers(
            $this->catalogKey($type, $dimension),
        );

        return is_array($values)
            ? array_values(array_filter($values, is_string(...)))
            : [];
    }

    /** @return array<string, float> */
    private function sortedSetScores(
        string $key,
        int $start,
        int $stop,
        bool $reverse = false,
    ): array {
        $connection = $this->connection();
        $method = $reverse ? 'zrevrange' : 'zrange';
        $scores = $connection instanceof PhpRedisConnection
            ? $connection->{$method}($key, $start, $stop, true)
            : $connection->{$method}($key, $start, $stop, [
                'withscores' => true,
            ]);

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

    private function sortedSetCount(string $key): int
    {
        return max(0, (int) $this->connection()->zcard($key));
    }

    private function setCount(string $key): int
    {
        return max(0, (int) $this->connection()->scard($key));
    }

    private function setContains(string $key, string $value): bool
    {
        return (bool) $this->connection()->sismember($key, $value);
    }

    private function sortedSetCountBetween(
        string $key,
        string $minimum,
        string $maximum,
    ): int {
        return max(
            0,
            (int) $this->connection()->zcount($key, $minimum, $maximum),
        );
    }

    private function sortedSetCountSince(string $key, float $since): int
    {
        return $this->sortedSetCountBetween(
            $key,
            '-inf',
            number_format(-$since, 6, '.', ''),
        );
    }

    private function difference(
        string $destination,
        string ...$keys,
    ): int {
        $this->transaction(function (mixed $transaction) use (
            $destination,
            $keys,
        ): void {
            $transaction->zdiffstore($destination, $keys);
            $transaction->expire(
                $destination,
                self::TEMPORARY_KEY_TTL_SECONDS,
            );
        });

        return $this->sortedSetCount($destination);
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, int>  $weights
     */
    private function intersect(
        mixed $transaction,
        string $destination,
        array $keys,
        array $weights,
    ): void {
        $transaction->zinterstore(
            $destination,
            $keys,
            $weights,
            'sum',
        );
    }

    /** @param array<int, string> $keys */
    private function union(
        mixed $transaction,
        string $destination,
        array $keys,
    ): void {
        $transaction->zunionstore(
            $destination,
            $keys,
            array_fill(0, count($keys), 1),
            'sum',
        );
    }

    /** @return array<string, array<int, string>> */
    private function facetValues(
        RetainedJobType $type,
        RetainedJobMetadata $metadata,
    ): array {
        $values = $metadata->facetValues();

        if ($type !== RetainedJobType::Failed) {
            unset($values['tag']);
        }

        if ($type !== RetainedJobType::Pending) {
            unset($values['target']);
        }

        return $values;
    }

    /** @return array<int, string> */
    private function requiredCatalogDimensions(
        RetainedJobType $type,
    ): array {
        return $type === RetainedJobType::Pending
            ? [...self::REQUIRED_CATALOG_DIMENSIONS, 'target']
            : self::REQUIRED_CATALOG_DIMENSIONS;
    }

    private function prepareWorkingGeneration(
        RetainedJobType $type,
        Closure $heartbeat,
    ): void {
        $publishedState = $this->publishedState($type);
        $publishedGeneration = $publishedState === null
            ? self::LEGACY_GENERATION
            : $this->generationFromPublishedState($publishedState);
        $workingGeneration = $publishedGeneration === self::GENERATIONS[0]
            ? self::GENERATIONS[1]
            : self::GENERATIONS[0];

        $this->workingGenerations[$type->value] = $workingGeneration;

        if ($this->generationIsInitialized($type, $workingGeneration)) {
            return;
        }

        $this->deleteGeneration($type, $workingGeneration, $heartbeat);
        $heartbeat();
        $this->transaction(function (mixed $transaction) use (
            $publishedGeneration,
            $type,
            $workingGeneration,
        ): void {
            $this->union(
                $transaction,
                $this->unresolvedKeyForGeneration(
                    $type,
                    $workingGeneration,
                ),
                [
                    $this->unresolvedKeyForGeneration(
                        $type,
                        $publishedGeneration,
                    ),
                ],
            );
        });
        $this->initializeGeneration($type, $workingGeneration);
    }

    private function publishWorkingGeneration(
        RetainedJobType $type,
        string $lockToken,
        string $sourceKey,
    ): void {
        $generation = $this->workingGenerations[$type->value] ?? null;

        if (! is_string($generation)) {
            throw new RuntimeException(
                'The retained job working generation is unavailable.',
            );
        }

        $publishedState = $this->publishedState($type);
        $replacedGeneration = $publishedState === null
            ? self::LEGACY_GENERATION
            : $this->generationFromPublishedState($publishedState);
        $this->publishGenerationState(
            $type,
            $generation,
            bin2hex(random_bytes(16)),
        );

        try {
            $this->removeUnretainedMetadataFromGeneration(
                $type,
                $replacedGeneration,
                $lockToken,
                $sourceKey,
            );
        } catch (Throwable) {
            // Shared metadata is cleaned again when the old slot is reused.
        }
    }

    private function removeUnretainedMetadataFromGeneration(
        RetainedJobType $type,
        string $generation,
        string $lockToken,
        string $sourceKey,
    ): void {
        $staleKey = $this->temporaryKey('replaced-generation-stale');

        try {
            $this->difference(
                $staleKey,
                $this->projectionKeyForGeneration($type, $generation),
                $sourceKey,
            );

            while (true) {
                $ids = $this->connection()->zrange(
                    $staleKey,
                    0,
                    self::SOURCE_CHUNK_SIZE - 1,
                );
                $ids = is_array($ids)
                    ? array_values(array_filter($ids, is_string(...)))
                    : [];

                if ($ids === []) {
                    break;
                }

                $this->renewWorkingSet(
                    $type,
                    $lockToken,
                    $staleKey,
                    $sourceKey,
                );
                $retained = $this->retainedByAnySource($ids);

                foreach ($ids as $id) {
                    if (! ($retained[$id] ?? true)) {
                        $this->connection()->hdel(
                            $this->metadataKey(),
                            $id,
                        );
                    }
                }

                $this->connection()->zrem($staleKey, ...$ids);
            }
        } finally {
            $this->deleteQuietly($staleKey);
        }
    }

    private function deleteGeneration(
        RetainedJobType $type,
        string $generation,
        ?Closure $heartbeat = null,
    ): void {
        foreach (self::FACET_DIMENSIONS as $dimension) {
            foreach ($this->catalogMemberChunksForGeneration(
                $type,
                $generation,
                $dimension,
            ) as $values) {
                $heartbeat?->__invoke();
                $this->connection()->del(...array_map(
                    fn (string $value): string => $this
                        ->facetKeyForGeneration(
                            $type,
                            $generation,
                            $dimension,
                            $value,
                        ),
                    $values,
                ));
            }

            $this->connection()->del(
                $this->catalogKeyForGeneration(
                    $type,
                    $generation,
                    $dimension,
                ),
            );
        }

        $heartbeat?->__invoke();
        $this->connection()->del(
            $this->projectionKeyForGeneration($type, $generation),
            $this->unresolvedKeyForGeneration($type, $generation),
            $this->generationInitializationKey($type, $generation),
        );
    }

    private function acquireSynchronizationLock(
        RetainedJobType $type,
    ): ?string {
        $token = bin2hex(random_bytes(16));
        $connection = $this->connection();
        $acquired = $connection instanceof PhpRedisConnection
            ? $connection->set(
                $this->lockKey($type),
                $token,
                'EX',
                self::SYNCHRONIZATION_LOCK_SECONDS,
                'NX',
            )
            : $connection->client()->set(
                $this->lockKey($type),
                $token,
                'EX',
                self::SYNCHRONIZATION_LOCK_SECONDS,
                'NX',
            );

        return $acquired === true
            || $acquired === 'OK'
            || ($acquired instanceof Status && $acquired->getPayload() === 'OK')
                ? $token
                : null;
    }

    private function publishGenerationState(
        RetainedJobType $type,
        string $generation,
        string $revision,
    ): void {
        $connection = $this->connection();

        if ($generation === self::LEGACY_GENERATION) {
            $revisionStored = $connection instanceof PhpRedisConnection
                ? $connection->set(
                    $this->synchronizationRevisionKey($type),
                    $revision,
                )
                : $connection->client()->set(
                    $this->synchronizationRevisionKey($type),
                    $revision,
                );

            if (
                $revisionStored !== true
                && $revisionStored !== 'OK'
                && ! (
                    $revisionStored instanceof Status
                    && $revisionStored->getPayload() === 'OK'
                )
            ) {
                throw new RuntimeException(
                    'The retained job synchronization revision could not be stored.',
                );
            }
        }

        $publishedState = "{$generation}:{$revision}";
        $published = $connection instanceof PhpRedisConnection
            ? $connection->set(
                $this->publishedGenerationKey($type),
                $publishedState,
            )
            : $connection->client()->set(
                $this->publishedGenerationKey($type),
                $publishedState,
            );

        if (
            $published !== true
            && $published !== 'OK'
            && ! (
                $published instanceof Status
                && $published->getPayload() === 'OK'
            )
        ) {
            throw new RuntimeException(
                'The retained job generation could not be published.',
            );
        }
    }

    private function ensureSynchronizationRevision(
        RetainedJobType $type,
    ): void {
        if ($this->publishedState($type) === null) {
            $this->publishGenerationState(
                $type,
                self::LEGACY_GENERATION,
                bin2hex(random_bytes(16)),
            );
        }
    }

    /** @phpstan-impure */
    private function synchronizationRevision(
        RetainedJobType $type,
    ): ?string {
        return $this->publishedState($type);
    }

    /** @phpstan-impure */
    private function publishedState(
        RetainedJobType $type,
    ): ?string {
        $publishedState = $this->connection()->get(
            $this->publishedGenerationKey($type),
        );

        if (
            is_string($publishedState)
            && $this->publishedStateIsValid($publishedState)
        ) {
            return $publishedState;
        }

        $legacyRevision = $this->connection()->get(
            $this->synchronizationRevisionKey($type),
        );

        return is_string($legacyRevision)
            ? self::LEGACY_GENERATION.":{$legacyRevision}"
            : null;
    }

    /** @phpstan-impure */
    private function synchronizationIsActive(
        RetainedJobType $type,
    ): bool {
        return is_string($this->connection()->get($this->lockKey($type)));
    }

    private function releaseSynchronizationLock(
        RetainedJobType $type,
        string $token,
    ): void {
        try {
            RedisScript::evaluate(
                $this->connection(),
                self::RELEASE_LOCK_SCRIPT,
                1,
                $this->lockKey($type),
                $token,
            );
        } catch (Throwable) {
            // The short lock expiry remains the cleanup fallback.
        }
    }

    private function renewWorkingSet(
        RetainedJobType $type,
        string $token,
        string $workingKey,
        ?string $sourceSnapshotKey = null,
    ): void {
        if (! $this->connection()->expire(
            $workingKey,
            self::TEMPORARY_KEY_TTL_SECONDS,
        )) {
            throw new RuntimeException(
                'The retained job synchronization working set expired.',
            );
        }

        if ($sourceSnapshotKey !== null) {
            $this->renewSourceSnapshot($sourceSnapshotKey);
        }

        $this->renewSynchronizationLock($type, $token);
    }

    private function renewSynchronizationState(
        RetainedJobType $type,
        string $token,
        string $sourceSnapshotKey,
    ): void {
        $this->renewSourceSnapshot($sourceSnapshotKey);
        $this->renewSynchronizationLock($type, $token);
    }

    private function renewSourceSnapshot(string $sourceSnapshotKey): void
    {
        if ($this->connection()->expire(
            $sourceSnapshotKey,
            self::SYNCHRONIZATION_LOCK_SECONDS,
        )) {
            return;
        }

        if (($this->sourceSnapshotCounts[$sourceSnapshotKey] ?? null) === 0) {
            return;
        }

        throw new RuntimeException(
            'The retained job synchronization source snapshot expired.',
        );
    }

    private function renewSynchronizationLock(
        RetainedJobType $type,
        string $token,
    ): void {
        $renewed = RedisScript::evaluate(
            $this->connection(),
            self::RENEW_LOCK_SCRIPT,
            1,
            $this->lockKey($type),
            $token,
            (string) self::SYNCHRONIZATION_LOCK_SECONDS,
        );

        if ((int) $renewed !== 1) {
            throw new RuntimeException(
                'The retained job synchronization lock expired.',
            );
        }
    }

    private function lockKey(RetainedJobType $type): string
    {
        return $this->key("{$type->value}:synchronize-lock");
    }

    private function currentGeneration(
        RetainedJobType $type,
    ): string {
        $workingGeneration = $this->workingGenerations[$type->value] ?? null;

        if (is_string($workingGeneration)) {
            return $workingGeneration;
        }

        if (
            $this->atomicGenerationSnapshot !== null
            && isset($this->atomicGenerationSnapshot[$type->value])
        ) {
            return $this->atomicGenerationSnapshot[$type->value];
        }

        $publishedState = $this->publishedState($type);

        return $publishedState === null
            ? self::LEGACY_GENERATION
            : $this->generationFromPublishedState($publishedState);
    }

    private function generationFromPublishedState(
        string $publishedState,
    ): string {
        return explode(':', $publishedState, 2)[0];
    }

    private function generationIsInitialized(
        RetainedJobType $type,
        string $generation,
    ): bool {
        return $this->connection()->get(
            $this->generationInitializationKey($type, $generation),
        ) === '1';
    }

    private function initializeGeneration(
        RetainedJobType $type,
        string $generation,
    ): void {
        $connection = $this->connection();
        $initialized = $connection instanceof PhpRedisConnection
            ? $connection->set(
                $this->generationInitializationKey($type, $generation),
                '1',
            )
            : $connection->client()->set(
                $this->generationInitializationKey($type, $generation),
                '1',
            );

        if (
            $initialized !== true
            && $initialized !== 'OK'
            && ! (
                $initialized instanceof Status
                && $initialized->getPayload() === 'OK'
            )
        ) {
            throw new RuntimeException(
                'The retained job generation could not be initialized.',
            );
        }
    }

    private function publishedStateIsValid(string $publishedState): bool
    {
        [$generation, $revision] = array_pad(
            explode(':', $publishedState, 2),
            2,
            null,
        );

        return in_array(
            $generation,
            [self::LEGACY_GENERATION, ...self::GENERATIONS],
            true,
        ) && is_string($revision) && $revision !== '';
    }

    private function projectionKeyForGeneration(
        RetainedJobType $type,
        string $generation,
    ): string {
        return $this->generationKey($type, $generation, 'projection');
    }

    private function unresolvedKeyForGeneration(
        RetainedJobType $type,
        string $generation,
    ): string {
        return $this->generationKey($type, $generation, 'unresolved');
    }

    private function facetKeyForGeneration(
        RetainedJobType $type,
        string $generation,
        string $dimension,
        string $value,
    ): string {
        return $this->generationKey(
            $type,
            $generation,
            "facet:{$dimension}:".hash('sha256', $value),
        );
    }

    private function catalogKeyForGeneration(
        RetainedJobType $type,
        string $generation,
        string $dimension,
    ): string {
        return $this->generationKey(
            $type,
            $generation,
            "catalog:{$dimension}",
        );
    }

    private function generationKey(
        RetainedJobType $type,
        string $generation,
        string $suffix,
    ): string {
        return $generation === self::LEGACY_GENERATION
            ? $this->key("{$type->value}:{$suffix}")
            : $this->key(
                "{$type->value}:generation:{$generation}:{$suffix}",
            );
    }

    private function publishedGenerationKey(
        RetainedJobType $type,
    ): string {
        return $this->key("{$type->value}:published-generation");
    }

    private function generationInitializationKey(
        RetainedJobType $type,
        string $generation,
    ): string {
        return $this->generationKey(
            $type,
            $generation,
            'initialized',
        );
    }

    private function synchronizationRevisionKey(
        RetainedJobType $type,
    ): string {
        return $this->key("{$type->value}:synchronize-revision");
    }

    private function temporaryKey(string $kind): string
    {
        return $this->key(
            "temporary:{$kind}:".bin2hex(random_bytes(12)),
        );
    }

    private function key(string $suffix): string
    {
        return $this->namespace().':'.$suffix;
    }

    private function namespace(): string
    {
        if ($this->namespace !== null) {
            return $this->namespace;
        }

        if (
            is_string($this->configuredNamespace)
            && $this->configuredNamespace !== ''
        ) {
            return $this->namespace = $this->configuredNamespace;
        }

        $horizonPrefix = config('horizon.prefix');
        $seed = is_string($horizonPrefix) ? $horizonPrefix : '';

        return $this->namespace = "\x1fhorizon-new-dawn:v2:"
            .substr(hash('sha256', $seed), 0, 32)
            .':jobs';
    }

    private function assertNamespaceOwnership(): void
    {
        $key = $this->key('owner');
        $owner = 'nckrtl/horizon-new-dawn:retained-jobs:v2';
        $this->connection()->setnx($key, $owner);

        if ($this->connection()->get($key) !== $owner) {
            throw new RuntimeException(
                'The retained job index Redis namespace is already in use.',
            );
        }
    }

    private function scoreLiteral(float $score): string
    {
        return sprintf('%.17g', $score);
    }

    private function connection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $connection = $this->redis->connection('horizon');

        if (
            $connection instanceof PhpRedisClusterConnection
            || $connection instanceof PredisClusterConnection
        ) {
            throw new RuntimeException(
                'Retained job queries do not support Redis Cluster.',
            );
        }

        $this->configurePredisKeyPrefixing($connection);

        return $this->connection = $connection;
    }

    private function configurePredisKeyPrefixing(Connection $connection): void
    {
        if (! $connection instanceof PredisConnection) {
            return;
        }

        $processor = $connection
            ->client()
            ->getCommandFactory()
            ->getProcessor();

        if (! $processor instanceof KeyPrefixProcessor) {
            return;
        }

        $processor->setCommandHandler(
            'ZDIFFSTORE',
            static function (
                CommandInterface $command,
                string $prefix,
            ): void {
                $arguments = $command->getArguments();
                $keyCount = (int) ($arguments[1] ?? 0);

                if (! is_string($arguments[0] ?? null) || $keyCount < 1) {
                    throw new RuntimeException(
                        'Predis could not prefix the retained job difference.',
                    );
                }

                $arguments[0] = $prefix.$arguments[0];

                for ($index = 2; $index < $keyCount + 2; $index++) {
                    if (! is_string($arguments[$index] ?? null)) {
                        throw new RuntimeException(
                            'Predis could not prefix the retained job difference.',
                        );
                    }

                    $arguments[$index] = $prefix.$arguments[$index];
                }

                $command->setRawArguments($arguments);
            },
        );
    }

    /** @return array<int, mixed> */
    private function pipeline(Closure $callback): array
    {
        return $this->withAtomicGenerationSnapshot(function () use ($callback): array {
            $connection = $this->connection();
            $results = $connection instanceof PhpRedisConnection
                ? $connection->pipeline($callback)
                : $connection->client()->pipeline($callback);

            return is_array($results) ? array_values($results) : [];
        });
    }

    private function transaction(Closure $callback): void
    {
        // MULTI/EXEC shares the connection. Any connection()->get() inside the
        // callback is queued by Redis but not tracked by Predis MultiExec, which
        // desynchronizes EXEC response counts. Snapshot generation keys first.
        $this->withAtomicGenerationSnapshot(function () use ($callback): null {
            $connection = $this->connection();

            if ($connection instanceof PhpRedisConnection) {
                $connection->transaction($callback);
            } else {
                $connection->client()->transaction($callback);
            }

            return null;
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withAtomicGenerationSnapshot(Closure $callback): mixed
    {
        $this->atomicGenerationDepth++;

        if ($this->atomicGenerationDepth === 1) {
            $snapshot = [];

            foreach (RetainedJobType::cases() as $type) {
                $snapshot[$type->value] = $this->currentGeneration($type);
            }

            $this->atomicGenerationSnapshot = $snapshot;
        }

        try {
            return $callback();
        } finally {
            $this->atomicGenerationDepth--;

            if ($this->atomicGenerationDepth === 0) {
                $this->atomicGenerationSnapshot = null;
            }
        }
    }

    private function deleteQuietly(string $key): void
    {
        unset($this->sourceSnapshotCounts[$key]);

        try {
            $this->connection()->del($key);
        } catch (Throwable) {
            // Temporary keys have a short TTL when deletion is unavailable.
        }
    }
}
