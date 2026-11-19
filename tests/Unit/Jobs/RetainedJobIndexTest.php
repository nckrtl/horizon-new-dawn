<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Mockery\MockInterface;
use NckRtl\HorizonNewDawn\Jobs\RetainedJobFilterCatalog;
use NckRtl\HorizonNewDawn\Jobs\RetainedJobIndex;
use NckRtl\HorizonNewDawn\Jobs\RetainedJobIndexWarming;
use NckRtl\HorizonNewDawn\Jobs\RetainedJobType;
use Predis\Client;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardExpects;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsUsing;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

final class RetainedJobIndexRedisClient extends Client
{
    /** @var array<string, array<string, float>> */
    public array $sortedSets = [];

    /** @var array<string, array<string, string>> */
    public array $hashes = [];

    /** @var array<string, array<string, true>> */
    public array $sets = [];

    /** @var array<string, string> */
    public array $strings = [];

    public int $differenceWrites = 0;

    public int $setMemberReads = 0;

    public int $setScanCalls = 0;

    public int $unionWrites = 0;

    public bool $failLockRenewal = false;

    public bool $treatEmptySortedSetsAsMissing = false;

    /** @var (Closure(self): void)|null */
    public ?Closure $afterNextPipeline = null;

    /** @var (Closure(self): void)|null */
    public ?Closure $afterNextSortedSetRange = null;

    private bool $recordPipelineResults = false;

    /** @var array<int, mixed> */
    private array $pipelineResults = [];

    public function __construct()
    {
        parent::__construct(options: ['prefix' => '']);
    }

    /** @param array<string, float|int> $members */
    public function seedSortedSet(string $key, array $members): void
    {
        $this->sortedSets[$key] = array_map(
            static fn (float|int $score): float => (float) $score,
            $members,
        );
    }

    /** @return array<int|string, float|string> */
    public function zrange(string $key, int $start, int $stop, mixed $options = null): array
    {
        return $this->sortedSetRange($key, $start, $stop, $options, false);
    }

    /** @return array<int|string, float|string> */
    public function zrevrange(string $key, int $start, int $stop, mixed $options = null): array
    {
        return $this->sortedSetRange($key, $start, $stop, $options, true);
    }

    public function zadd(string $key, float|int|string $score, string $member): int
    {
        $created = ! isset($this->sortedSets[$key][$member]);
        $this->sortedSets[$key][$member] = (float) $score;

        return $created ? 1 : 0;
    }

    public function zrem(string $key, string ...$members): int
    {
        $removed = 0;

        foreach ($members as $member) {
            if (isset($this->sortedSets[$key][$member])) {
                $removed++;
            }

            unset($this->sortedSets[$key][$member]);
        }

        return $removed;
    }

    public function zscore(string $key, string $member): float|false
    {
        $score = $this->sortedSets[$key][$member] ?? false;

        if ($this->recordPipelineResults) {
            $this->pipelineResults[] = $score;
        }

        return $score;
    }

    public function zcard(string $key): int
    {
        $count = count($this->sortedSets[$key] ?? []);

        if ($this->recordPipelineResults) {
            $this->pipelineResults[] = $count;
        }

        return $count;
    }

    public function zcount(string $key, float|int|string $min, float|int|string $max): int
    {
        $minimumExclusive = is_string($min) && str_starts_with($min, '(');
        $maximumExclusive = is_string($max) && str_starts_with($max, '(');
        $minimum = $minimumExclusive ? substr($min, 1) : $min;
        $maximum = $maximumExclusive ? substr($max, 1) : $max;

        return count(array_filter(
            $this->sortedSets[$key] ?? [],
            static fn (float $score): bool => ($minimum === '-inf'
                || ($minimumExclusive ? $score > (float) $minimum : $score >= (float) $minimum))
                && ($maximum === '+inf'
                    || ($maximumExclusive ? $score < (float) $maximum : $score <= (float) $maximum)),
        ));
    }

    /** @param array<int, string> $keys */
    public function zdiffstore(string $destination, array $keys): int
    {
        $this->differenceWrites++;
        $members = $this->sortedSets[$keys[0]] ?? [];

        foreach (array_slice($keys, 1) as $key) {
            $members = array_diff_key($members, $this->sortedSets[$key] ?? []);
        }

        if ($this->treatEmptySortedSetsAsMissing && $members === []) {
            unset($this->sortedSets[$destination]);
        } else {
            $this->sortedSets[$destination] = $members;
        }

        return count($members);
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, int>  $weights
     */
    public function zinterstore(
        string $destination,
        array $keys,
        array $weights = [],
        string $aggregate = 'sum',
    ): int {
        $members = $this->sortedSets[$keys[0]] ?? [];

        foreach (array_slice($keys, 1) as $key) {
            $members = array_intersect_key($members, $this->sortedSets[$key] ?? []);
        }

        foreach ($members as $member => $score) {
            $weighted = 0.0;

            foreach ($keys as $index => $key) {
                $weighted += ($this->sortedSets[$key][$member] ?? 0)
                    * ($weights[$index] ?? 1);
            }

            $members[$member] = $weighted;
        }

        if ($this->treatEmptySortedSetsAsMissing && $members === []) {
            unset($this->sortedSets[$destination]);
        } else {
            $this->sortedSets[$destination] = $members;
        }

        return count($members);
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, int>  $weights
     */
    public function zunionstore(
        string $destination,
        array $keys,
        array $weights = [],
        string $aggregate = 'sum',
    ): int {
        $this->unionWrites++;
        $members = [];

        foreach ($keys as $index => $key) {
            foreach ($this->sortedSets[$key] ?? [] as $member => $score) {
                $weightedScore = $score * ($weights[$index] ?? 1);
                $members[$member] = ($members[$member] ?? 0) + $weightedScore;
            }
        }

        if ($this->treatEmptySortedSetsAsMissing && $members === []) {
            unset($this->sortedSets[$destination]);
        } else {
            $this->sortedSets[$destination] = $members;
        }

        return count($members);
    }

    public function hset(string $key, string $field, string $value): int
    {
        $created = ! isset($this->hashes[$key][$field]);
        $this->hashes[$key][$field] = $value;

        return $created ? 1 : 0;
    }

    public function hget(string $key, string $field): string|false
    {
        return $this->hashes[$key][$field] ?? false;
    }

    public function hlen(string $key): int
    {
        return count($this->hashes[$key] ?? []);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, string|false>
     */
    public function hmget(string $key, array $fields): array
    {
        return array_map(
            fn (string $field): string|false => $this->hashes[$key][$field] ?? false,
            $fields,
        );
    }

    public function hdel(string $key, string $field): int
    {
        $exists = isset($this->hashes[$key][$field]);
        unset($this->hashes[$key][$field]);

        return $exists ? 1 : 0;
    }

    public function sadd(string $key, string $value): int
    {
        $created = ! isset($this->sets[$key][$value]);
        $this->sets[$key][$value] = true;

        return $created ? 1 : 0;
    }

    /** @return array<int, string> */
    public function smembers(string $key): array
    {
        $this->setMemberReads++;

        return array_keys($this->sets[$key] ?? []);
    }

    /**
     * @param  array{count?: int}  $options
     * @return array{int, array<int, string>}|false
     */
    public function sscan(
        string $key,
        int|string $cursor,
        array $options = [],
    ): array|false {
        $this->setScanCalls++;

        if ((string) $cursor !== '0') {
            return false;
        }

        $members = array_keys($this->sets[$key] ?? []);

        return $members === [] ? false : [0, $members];
    }

    public function srem(string $key, string $value): int
    {
        $exists = isset($this->sets[$key][$value]);
        unset($this->sets[$key][$value]);

        return $exists ? 1 : 0;
    }

    public function scard(string $key): int
    {
        return count($this->sets[$key] ?? []);
    }

    public function sismember(string $key, string $value): bool
    {
        return isset($this->sets[$key][$value]);
    }

    public function expire(string $key, int $seconds): bool
    {
        if ($this->treatEmptySortedSetsAsMissing) {
            return isset($this->sortedSets[$key])
                || isset($this->sets[$key])
                || isset($this->hashes[$key])
                || isset($this->strings[$key]);
        }

        return true;
    }

    public function del(string ...$keys): int
    {
        $removed = 0;

        foreach ($keys as $key) {
            $exists = isset($this->sortedSets[$key])
                || isset($this->sets[$key])
                || isset($this->hashes[$key])
                || isset($this->strings[$key]);
            unset(
                $this->sortedSets[$key],
                $this->sets[$key],
                $this->hashes[$key],
                $this->strings[$key],
            );
            $removed += $exists ? 1 : 0;
        }

        return $removed;
    }

    public function setnx(string $key, string $value): int
    {
        if (isset($this->strings[$key])) {
            return 0;
        }

        $this->strings[$key] = $value;

        return 1;
    }

    public function get(string $key): string|false
    {
        return $this->strings[$key] ?? false;
    }

    public function eval(
        string $script,
        int $keyCount,
        string $key,
        string $token,
        string $seconds = '',
    ): int|string|false {
        if (str_contains($script, 'local boundary')) {
            return $this->strings[$key] ?? false;
        }

        if (($this->strings[$key] ?? null) !== $token) {
            return 0;
        }

        if (str_contains($script, "redis.call('expire'")) {
            return $this->failLockRenewal ? 0 : 1;
        }

        unset($this->strings[$key]);

        return 1;
    }

    public function set(
        string $key,
        string $value,
        mixed ...$arguments,
    ): bool {
        if (
            in_array('NX', $arguments, true)
            && isset($this->strings[$key])
        ) {
            return false;
        }

        $this->strings[$key] = $value;

        return true;
    }

    /** @return array<int, mixed> */
    public function pipeline(...$arguments): array
    {
        $callback = $arguments[0] ?? null;
        $this->recordPipelineResults = true;
        $this->pipelineResults = [];

        if ($callback instanceof Closure) {
            $callback($this);
        }

        $this->recordPipelineResults = false;
        $results = $this->pipelineResults;
        $afterPipeline = $this->afterNextPipeline;
        $this->afterNextPipeline = null;

        if ($afterPipeline instanceof Closure) {
            $afterPipeline($this);
        }

        return $results;
    }

    /** @return array<int, mixed> */
    public function transaction(...$arguments): array
    {
        $callback = $arguments[0] ?? null;

        if ($callback instanceof Closure) {
            $callback($this);
        }

        return [];
    }

    /**
     * @return array<int|string, float|string>
     */
    private function sortedSetRange(
        string $key,
        int $start,
        int $stop,
        mixed $options,
        bool $reverse,
    ): array {
        $members = $this->sortedSets[$key] ?? [];
        uksort(
            $members,
            static function (string $left, string $right) use ($members): int {
                $score = $members[$left] <=> $members[$right];

                return $score !== 0 ? $score : strcmp($left, $right);
            },
        );

        if ($reverse) {
            $members = array_reverse($members, true);
        }

        $length = $stop < 0 ? null : max(0, $stop - $start + 1);
        $slice = array_slice($members, $start, $length, true);
        $withScores = $options === true
            || (is_array($options) && ($options['withscores'] ?? false) === true);
        $afterRange = $this->afterNextSortedSetRange;
        $this->afterNextSortedSetRange = null;

        if ($afterRange instanceof Closure) {
            $afterRange($this);
        }

        return $withScores ? $slice : array_keys($slice);
    }
}

function retainedJobIndexRedisFactory(RetainedJobIndexRedisClient $client): RedisFactory
{
    $redis = mockDashboardContract(RedisFactory::class);
    dashboardReturnsFor($redis, 'connection', ['horizon'], new PredisConnection($client));

    return $redis;
}

/** @return JobRepository&MockInterface */
function retainedJobIndexRepository(): JobRepository
{
    $repository = mockDashboardContract(JobRepository::class);
    dashboardExpects(
        $repository,
        'trimRecentJobs',
        times: 'zeroOrMoreTimes',
    );
    dashboardExpects(
        $repository,
        'trimFailedJobs',
        times: 'zeroOrMoreTimes',
    );

    return $repository;
}

/**
 * @return array{
 *     client: RetainedJobIndexRedisClient,
 *     index: RetainedJobIndex,
 *     hydrations: array<int, array<int, string>>
 * }
 */
function retainedJobIntegrityFixture(): array
{
    $client = new RetainedJobIndexRedisClient;
    $client->seedSortedSet('completed_jobs', [
        'exact-job' => -1,
        'null-job' => -2,
    ]);
    $hydrations = [];
    $repository = retainedJobIndexRepository();
    dashboardReturnsUsing(
        $repository,
        'getJobs',
        static function (array $ids) use (&$hydrations): Collection {
            $hydrations[] = $ids;

            return new Collection(array_map(
                static function (string $id, int $offset): object {
                    $job = horizonJob($offset, $id);

                    if ($id === 'null-job') {
                        $job->completed_at = null;
                        $job->reserved_at = '';
                    }

                    return $job;
                },
                $ids,
                array_keys($ids),
            ));
        },
    );
    $index = new RetainedJobIndex(
        retainedJobIndexRedisFactory($client),
        $repository,
    );
    $index->synchronize(RetainedJobType::Completed);

    return [
        'client' => $client,
        'index' => $index,
        'hydrations' => &$hydrations,
    ];
}

describe('RetainedJobIndex', function (): void {
    it('keeps its namespace stable across application key rotation and isolated by Horizon prefix', function (): void {
        $redis = mockDashboardContract(RedisFactory::class);
        $repository = retainedJobIndexRepository();
        $originalApplicationKey = config('app.key');
        $originalHorizonPrefix = config('horizon.prefix');

        try {
            config([
                'app.key' => 'base64:first-application-key',
                'horizon.prefix' => 'horizon:first:',
            ]);
            $beforeRotation = (new RetainedJobIndex(
                $redis,
                $repository,
            ))->metadataKey();
            config(['app.key' => 'base64:rotated-application-key']);
            $afterRotation = (new RetainedJobIndex(
                $redis,
                $repository,
            ))->metadataKey();
            config(['horizon.prefix' => 'horizon:second:']);
            $otherApplication = (new RetainedJobIndex(
                $redis,
                $repository,
            ))->metadataKey();

            expect($afterRotation)->toBe($beforeRotation)
                ->and($otherApplication)->not->toBe($beforeRotation);
        } finally {
            config([
                'app.key' => $originalApplicationKey,
                'horizon.prefix' => $originalHorizonPrefix,
            ]);
        }
    });

    it('hydrates missing IDs in chunks and never rehydrates unchanged metadata', function (): void {
        $ids = array_map(
            static fn (int $index): string => "pending-{$index}",
            range(0, 100),
        );
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet(
            'pending_jobs',
            array_combine($ids, array_map(static fn (int $index): float => -$index, range(1, 101))),
        );

        $hydrations = [];
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $requestedIds) use (&$hydrations): Collection {
                $hydrations[] = $requestedIds;

                return new Collection(array_map(
                    static fn (string $id, int $index): object => horizonJob($index, $id),
                    $requestedIds,
                    array_keys($requestedIds),
                ));
            },
        );

        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
            hydrationChunkSize: 100,
        );

        $index->synchronize(RetainedJobType::Pending);
        $index->synchronize(RetainedJobType::Pending, force: true);

        expect($hydrations)->toHaveCount(2)
            ->and($hydrations[0])->toHaveCount(100)
            ->and($hydrations[1])->toHaveCount(1)
            ->and($client->hashes[$index->metadataKey()] ?? [])->toHaveCount(101)
            ->and($client->sortedSets[$index->projectionKey(RetainedJobType::Pending)] ?? [])
            ->toHaveCount(101);
    });

    it('indexes queue targets only for pending jobs', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $client->seedSortedSet('pending_jobs', ['pending-job' => -1]);

        $index->synchronize(RetainedJobType::Pending);

        expect($client->scard($index->catalogKey(
            RetainedJobType::Completed,
            'target',
        )))->toBe(0)
            ->and($client->scard($index->catalogKey(
                RetainedJobType::Pending,
                'target',
            )))->toBe(1);
    });

    it('rebuilds a missing projection from the retained source', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $client->del($index->projectionKey(RetainedJobType::Completed));

        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($client->zcard(
            $index->projectionKey(RetainedJobType::Completed),
        ))->toBe(2)
            ->and($fixture['hydrations'])->toHaveCount(1);
    });

    it('does not rotate the synchronization revision when a fresh instance finds an exact index', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $firstRequest = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $firstRequest->synchronize(RetainedJobType::Completed);
        $revision = $firstRequest->publishedRevision(
            RetainedJobType::Completed,
        );

        $secondRequest = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $secondRequest->synchronize(RetainedJobType::Completed);

        expect($revision)->toBeString()
            ->and($secondRequest->publishedRevision(
                RetainedJobType::Completed,
            ))->toBe($revision);
    });

    it('keeps the previous generation readable while its replacement is synchronizing', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $repository = retainedJobIndexRepository();
        $probe = new class
        {
            public ?RetainedJobIndex $reader = null;

            public ?string $publishedRevision = null;

            /** @var array<int, string> */
            public array $pageIds = [];

            public ?int $pageTotal = null;

            /** @var array{total: int, hour: int, day: int}|array{} */
            public array $counts = [];
        };

        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use ($probe): Collection {
                if (
                    in_array('job-2', $ids, true)
                    && $probe->reader instanceof RetainedJobIndex
                ) {
                    $probe->publishedRevision = $probe->reader
                        ->publishedRevision(RetainedJobType::Completed);
                    $page = $probe->reader
                        ->pageIdsFromPublishedIndex(
                            RetainedJobType::Completed,
                            [],
                            null,
                            null,
                            50,
                        );
                    $probe->pageIds = $page['ids'];
                    $probe->pageTotal = $page['total'];
                    $probe->counts = $probe->reader
                        ->periodCountsFromPublishedIndex(
                            RetainedJobType::Completed,
                            [],
                            0,
                            0,
                        );
                }

                return new Collection(array_map(
                    static fn (string $id, int $index): object => horizonJob(
                        $index,
                        $id,
                    ),
                    $ids,
                    array_keys($ids),
                ));
            },
        );
        $writer = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $writer->synchronize(RetainedJobType::Completed);
        $initialRevision = $writer->publishedRevision(
            RetainedJobType::Completed,
        );
        $probe->reader = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
        ]);

        $writer->synchronize(RetainedJobType::Completed, force: true);

        expect($initialRevision)->toBeString()
            ->and($probe->publishedRevision)->toBe($initialRevision)
            ->and($probe->pageIds)->toBe(['job-1'])
            ->and($probe->pageTotal)->toBe(1)
            ->and($probe->counts)->toBe([
                'total' => 1,
                'hour' => 1,
                'day' => 1,
            ])
            ->and($writer->publishedRevision(
                RetainedJobType::Completed,
            ))->not->toBe($initialRevision)
            ->and($writer->pageIdsFromPublishedIndex(
                RetainedJobType::Completed,
                [],
                null,
                null,
                50,
            )['total'])->toBe(2);
    });

    it('does not publish an incomplete replacement generation', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $repository = retainedJobIndexRepository();
        $state = new class
        {
            public bool $failHydration = false;
        };

        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use ($state): Collection {
                if (
                    $state->failHydration
                    && in_array('job-2', $ids, true)
                ) {
                    throw new RuntimeException('Hydration failed.');
                }

                return new Collection(array_map(
                    static fn (string $id, int $index): object => horizonJob(
                        $index,
                        $id,
                    ),
                    $ids,
                    array_keys($ids),
                ));
            },
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Completed);
        $initialRevision = $index->publishedRevision(
            RetainedJobType::Completed,
        );
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
        ]);
        $state->failHydration = true;

        expect(
            fn () => $index->synchronize(
                RetainedJobType::Completed,
                force: true,
            ),
        )->toThrow(RuntimeException::class, 'Hydration failed.')
            ->and($index->publishedRevision(
                RetainedJobType::Completed,
            ))->toBe($initialRevision)
            ->and($index->pageIdsFromPublishedIndex(
                RetainedJobType::Completed,
                [],
                null,
                null,
                50,
            )['ids'])->toBe(['job-1']);

        $state->failHydration = false;
        $index->synchronize(RetainedJobType::Completed);

        expect($index->publishedRevision(
            RetainedJobType::Completed,
        ))->not->toBe($initialRevision)
            ->and($index->pageIdsFromPublishedIndex(
                RetainedJobType::Completed,
                [],
                null,
                null,
                50,
            )['total'])->toBe(2);
    });

    it('rejects a published read whose generation changes before the read completes', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob(
                    $index,
                    $id,
                ),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Completed);
        $publishedGenerationKey = str_replace(
            ':metadata',
            ':completed:published-generation',
            $index->metadataKey(),
        );
        $client->afterNextSortedSetRange = static function (
            RetainedJobIndexRedisClient $client,
        ) use ($publishedGenerationKey): void {
            $client->strings[$publishedGenerationKey] =
                'b:replacement-generation';
        };

        expect(
            fn () => $index->pageIdsFromPublishedIndex(
                RetainedJobType::Completed,
                [],
                null,
                null,
                50,
            ),
        )->toThrow(RetainedJobIndexWarming::class);
    });

    it('reconciles same-cardinality replacements without a forced synchronization', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
        ]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob(
                    $index,
                    $id,
                ),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Completed);
        $index->synchronize(RetainedJobType::Completed, force: true);
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-3' => -30,
        ]);

        $index->synchronize(RetainedJobType::Completed);

        expect($index->pageIdsFromPublishedIndex(
            RetainedJobType::Completed,
            [],
            null,
            null,
            50,
        )['ids'])->toEqualCanonicalizing(['job-1', 'job-3'])
            ->and($client->zscore(
                $index->projectionKey(RetainedJobType::Completed),
                'job-2',
            ))->toBeFalse();
    });

    it('detects and reconciles removal-only source changes', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
        ]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob(
                    $index,
                    $id,
                ),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Completed);
        $index->synchronize(RetainedJobType::Completed, force: true);
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);

        $index->synchronize(RetainedJobType::Completed);

        expect($index->pageIdsFromPublishedIndex(
            RetainedJobType::Completed,
            [],
            null,
            null,
            50,
        )['ids'])->toBe(['job-1']);
    });

    it('reuses the inactive generation and hydrates only appended records', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $repository = retainedJobIndexRepository();
        $hydrations = [];
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use (&$hydrations): Collection {
                $hydrations[] = $ids;

                return new Collection(array_map(
                    static fn (string $id, int $index): object => horizonJob(
                        $index,
                        $id,
                    ),
                    $ids,
                    array_keys($ids),
                ));
            },
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Completed);
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
        ]);
        $index->synchronize(RetainedJobType::Completed);
        $unionWritesBeforeReuse = $client->unionWrites;
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
            'job-3' => -30,
        ]);

        $index->synchronize(RetainedJobType::Completed);

        expect($hydrations)->toBe([
            ['job-1'],
            ['job-2'],
            ['job-3'],
        ])->and($client->unionWrites)->toBe($unionWritesBeforeReuse)
            ->and($index->pageIdsFromPublishedIndex(
                RetainedJobType::Completed,
                [],
                null,
                null,
                50,
            )['total'])->toBe(3);
    });

    it('fails forced cleanup when another synchronization owns the lock', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['stale-job' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $initialRequest = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $initialRequest->synchronize(RetainedJobType::Completed);
        $client->seedSortedSet('completed_jobs', []);
        $lockKey = str_replace(
            ':metadata',
            ':completed:synchronize-lock',
            $initialRequest->metadataKey(),
        );
        $client->strings[$lockKey] = 'another-synchronization';
        $maintenanceRequest = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        expect(
            fn () => $maintenanceRequest->synchronize(
                RetainedJobType::Completed,
                force: true,
            ),
        )->toThrow(RetainedJobIndexWarming::class)
            ->and($client->sortedSets[
                $initialRequest->projectionKey(RetainedJobType::Completed)
            ] ?? [])->toHaveKey('stale-job')
            ->and($client->strings[$lockKey])
            ->toBe('another-synchronization');
    });

    it('reports a warming index when a request finds another synchronization building it', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['completed-job' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $request = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $lockKey = str_replace(
            ':metadata',
            ':completed:synchronize-lock',
            $request->metadataKey(),
        );
        $client->strings[$lockKey] = 'another-synchronization';

        expect(
            fn () => $request->synchronize(RetainedJobType::Completed),
        )->toThrow(RetainedJobIndexWarming::class)
            ->and($client->strings[$lockKey])
            ->toBe('another-synchronization');
    });

    it('scans facet catalogs in bounded rebuild chunks', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $client->del($index->catalogKey(
            RetainedJobType::Completed,
            'job',
        ));
        $client->setMemberReads = 0;
        $client->setScanCalls = 0;

        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($client->setMemberReads)->toBe(0)
            ->and($client->setScanCalls)->toBe(5)
            ->and($client->zcard(
                $index->projectionKey(RetainedJobType::Completed),
            ))->toBe(2);
    });

    it('rebuilds a requested facet whose catalog membership survives key loss', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $name = 'App\\Jobs\\ImportFeed';
        $client->del($index->facetKey(
            RetainedJobType::Completed,
            'job',
            $name,
        ));

        expect($index->count(
            RetainedJobType::Completed,
            ['job' => $name],
        ))->toBe(2)
            ->and($fixture['hydrations'])->toHaveCount(1);
    });

    it('rebuilds a requested facet whose catalog membership was lost', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $name = 'App\\Jobs\\ImportFeed';
        $client->srem(
            $index->catalogKey(RetainedJobType::Completed, 'job'),
            $name,
        );

        expect($index->count(
            RetainedJobType::Completed,
            ['job' => $name],
        ))->toBe(2)
            ->and($client->sets[
                $index->catalogKey(RetainedJobType::Completed, 'job')
            ] ?? [])->toHaveKey($name)
            ->and($fixture['hydrations'])->toHaveCount(1);
    });

    it('rebuilds an empty exact-one catalog for a nonempty projection', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $client->del(
            $index->catalogKey(RetainedJobType::Completed, 'queue'),
        );

        expect($index->catalogValues(
            RetainedJobType::Completed,
            'queue',
        ))->toBe(['default'])
            ->and($client->sets[
                $index->catalogKey(RetainedJobType::Completed, 'queue')
            ] ?? [])->toHaveKey('default')
            ->and($fixture['hydrations'])->toHaveCount(1);
    });

    it('returns exact zero for an unknown facet absent from both catalog and index', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $index = $fixture['index'];

        expect($index->count(
            RetainedJobType::Completed,
            ['job' => 'App\\Jobs\\NeverSeen'],
        ))->toBe(0)
            ->and($fixture['hydrations'])->toHaveCount(1);
    });

    it('excludes facet membership outside the retained source', function (): void {
        $fixture = retainedJobIntegrityFixture();
        $client = $fixture['client'];
        $index = $fixture['index'];
        $value = 'tenant:concurrent';
        $catalogKey = $index->catalogKey(
            RetainedJobType::Completed,
            'tag',
        );
        $facetKey = $index->facetKey(
            RetainedJobType::Completed,
            'tag',
            $value,
        );
        $client->sadd($catalogKey, $value);
        $client->afterNextPipeline = static function (
            RetainedJobIndexRedisClient $redis,
        ) use ($facetKey): void {
            $redis->zadd($facetKey, -3, 'concurrent-job');
        };

        $firstRead = $index->catalogValues(
            RetainedJobType::Completed,
            'tag',
        );
        $nextRead = $index->catalogValues(
            RetainedJobType::Completed,
            'tag',
        );

        expect($firstRead)->toBe([])
            ->and($nextRead)->toBe([])
            ->and($client->sets[$catalogKey] ?? [])->toHaveKey($value)
            ->and($client->sortedSets[$facetKey] ?? [])
            ->toHaveKey('concurrent-job');
    });

    it('allows an empty failed tag catalog for a nonempty projection', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('failed_jobs', ['failed-job' => -1]);
        $hydrations = 0;
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use (&$hydrations): Collection {
                $hydrations++;
                $job = horizonJob(0, $ids[0]);
                $job->status = 'failed';
                $job->completed_at = null;
                $job->failed_at = '1800000000';
                $payload = json_decode(
                    $job->payload,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
                $payload['tags'] = [];
                $job->payload = json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR,
                );

                return new Collection([$job]);
            },
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        $index->synchronize(RetainedJobType::Failed);
        $index->synchronize(RetainedJobType::Failed, force: true);

        expect($client->scard($index->catalogKey(
            RetainedJobType::Failed,
            'tag',
        )))->toBe(0)
            ->and($hydrations)->toBe(1);
    });

    it('still reconciles a same-cardinality source replacement exactly', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['old-job' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        $index->synchronize(RetainedJobType::Completed);
        $client->seedSortedSet('completed_jobs', ['new-job' => -20]);
        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($client->sortedSets[$index->projectionKey(RetainedJobType::Completed)] ?? [])
            ->toBe(['new-job' => -20.0]);
    });

    it('cleans stale projection rows when the frozen retained source is empty', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['stale-job' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        $index->synchronize(RetainedJobType::Completed);
        $client->seedSortedSet('completed_jobs', []);
        $client->treatEmptySortedSetsAsMissing = true;
        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($client->sortedSets[
            $index->projectionKey(RetainedJobType::Completed)
        ] ?? [])->toBe([]);
    });

    it('proves a pure source append cannot require a stale projection scan', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        $index->synchronize(RetainedJobType::Completed);
        $client->seedSortedSet('completed_jobs', [
            'job-1' => -10,
            'job-2' => -20,
        ]);
        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($client->sortedSets[
            $index->projectionKey(RetainedJobType::Completed)
        ] ?? [])->toHaveKeys(['job-1', 'job-2']);
    });

    it('fails closed when the synchronization lease expires and can retry cleanly', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['job-1' => -10]);
        $client->failLockRenewal = true;
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob($index, $id),
                $ids,
                array_keys($ids),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        expect(fn () => $index->synchronize(RetainedJobType::Completed))
            ->toThrow(RuntimeException::class, 'synchronization lock expired')
            ->and($client->sortedSets[
                $index->projectionKey(RetainedJobType::Completed)
            ] ?? [])->toBe([]);

        $client->failLockRenewal = false;
        $index->synchronize(RetainedJobType::Completed);

        expect($client->sortedSets[
            $index->projectionKey(RetainedJobType::Completed)
        ] ?? [])->toHaveKey('job-1');
    });

    it('reuses immutable metadata across a source transition', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('pending_jobs', ['job-1' => -10]);
        $client->seedSortedSet('completed_jobs', []);

        $hydrations = 0;
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use (&$hydrations): Collection {
                $hydrations += count($ids);

                return new Collection([horizonJob(0, 'job-1')]);
            },
        );

        $index = new RetainedJobIndex(retainedJobIndexRedisFactory($client), $repository);
        $index->synchronize(RetainedJobType::Pending);

        $client->seedSortedSet('pending_jobs', []);
        $client->seedSortedSet('completed_jobs', ['job-1' => -20]);

        $index->synchronize(RetainedJobType::Pending, force: true);
        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($hydrations)->toBe(1)
            ->and($client->sortedSets[$index->projectionKey(RetainedJobType::Pending)] ?? [])
            ->not->toHaveKey('job-1')
            ->and($client->sortedSets[$index->projectionKey(RetainedJobType::Completed)] ?? [])
            ->toHaveKey('job-1')
            ->and($client->hashes[$index->metadataKey()] ?? [])
            ->toHaveKey('job-1');
    });

    it('falls back to repository hydration when cached metadata is corrupt', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('pending_jobs', ['job-1' => -10]);
        $client->seedSortedSet('completed_jobs', []);
        $hydrations = 0;
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use (&$hydrations): Collection {
                $hydrations += count($ids);
                $job = horizonJob(0, 'job-1');
                $job->name = 'App\\Jobs\\RecoveredJob';

                return new Collection([$job]);
            },
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Pending);
        $client->hashes[$index->metadataKey()]['job-1'] = '{invalid';
        $client->seedSortedSet('completed_jobs', ['job-1' => -20]);

        $index->synchronize(RetainedJobType::Completed);

        expect($hydrations)->toBe(2)
            ->and($index->catalogValues(
                RetainedJobType::Completed,
                'job',
            ))->toBe(['App\\Jobs\\RecoveredJob'])
            ->and($client->hashes[$index->metadataKey()]['job-1'])
            ->not->toBe('{invalid');
    });

    it('reuses cached tags when a retained job moves into failed history', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('pending_jobs', ['job-1' => -10]);
        $client->seedSortedSet('failed_jobs', []);
        $hydrations = 0;
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $ids) use (&$hydrations): Collection {
                $hydrations += count($ids);
                $job = horizonJob(0, 'job-1');
                $payload = json_decode(
                    $job->payload,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
                $payload['tags'] = ['tenant:42'];
                $job->payload = json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR,
                );

                return new Collection([$job]);
            },
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );
        $index->synchronize(RetainedJobType::Pending);
        $client->seedSortedSet('failed_jobs', ['job-1' => -20]);

        $index->synchronize(RetainedJobType::Failed);

        expect($hydrations)->toBe(1)
            ->and($index->catalogValues(
                RetainedJobType::Failed,
                'tag',
            ))->toBe(['tenant:42'])
            ->and($client->sortedSets[$index->facetKey(
                RetainedJobType::Failed,
                'tag',
                'tenant:42',
            )] ?? [])->toHaveKey('job-1');
    });

    it('removes stale source IDs and their unreferenced metadata', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['stale-job' => -10]);

        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (): Collection => new Collection([horizonJob(0, 'stale-job')]),
        );

        $index = new RetainedJobIndex(retainedJobIndexRedisFactory($client), $repository);
        $index->synchronize(RetainedJobType::Completed);
        $client->seedSortedSet('completed_jobs', []);
        $index->synchronize(RetainedJobType::Completed, force: true);

        expect($client->sortedSets[$index->projectionKey(RetainedJobType::Completed)] ?? [])
            ->not->toHaveKey('stale-job')
            ->and($client->hashes[$index->metadataKey()] ?? [])
            ->not->toHaveKey('stale-job');
    });

    it('converges when Horizon expires retained metadata during a long rebuild', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $expiringScore = -microtime(true);
        $stableScore = $expiringScore - 1;
        $client->seedSortedSet('completed_jobs', [
            'stable-job' => $stableScore,
            'expires-during-rebuild' => $expiringScore,
        ]);
        $trimCalls = 0;
        $repository = mockDashboardContract(JobRepository::class);
        dashboardExpects(
            $repository,
            'trimRecentJobs',
            times: 'twice',
            returnUsing: static function () use (&$trimCalls, $client): void {
                $trimCalls++;

                if ($trimCalls === 2) {
                    $client->zrem(
                        'completed_jobs',
                        'expires-during-rebuild',
                    );
                }
            },
        );
        dashboardExpects(
            $repository,
            'trimFailedJobs',
            times: 'zeroOrMoreTimes',
        );
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static fn (array $ids): Collection => new Collection(array_map(
                static fn (string $id, int $index): object => horizonJob(
                    $index,
                    $id,
                ),
                array_values(array_filter(
                    $ids,
                    static fn (string $id): bool => $id === 'stable-job',
                )),
                array_keys(array_values(array_filter(
                    $ids,
                    static fn (string $id): bool => $id === 'stable-job',
                ))),
            )),
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        $index->synchronize(RetainedJobType::Completed);

        expect($client->sortedSets['completed_jobs'] ?? [])
            ->toBe(['stable-job' => $stableScore])
            ->and($client->sortedSets[
                $index->projectionKey(RetainedJobType::Completed)
            ] ?? [])->toBe(['stable-job' => $stableScore])
            ->and($trimCalls)->toBe(2);
    });

    it('tracks uninspectable retained references without rebuilding them forever', function (): void {
        $client = new RetainedJobIndexRedisClient;
        $client->seedSortedSet('completed_jobs', ['expired-job' => -10]);
        $hydrations = 0;
        $repository = retainedJobIndexRepository();
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function () use (&$hydrations): Collection {
                $hydrations++;

                return new Collection;
            },
        );
        $index = new RetainedJobIndex(
            retainedJobIndexRedisFactory($client),
            $repository,
        );

        $index->synchronize(RetainedJobType::Completed);
        $index->synchronize(RetainedJobType::Completed, force: true);
        $catalog = (new RetainedJobFilterCatalog($index))->for(
            RetainedJobType::Completed,
        );

        expect($index->unresolvedCount(RetainedJobType::Completed))->toBe(1)
            ->and($index->catalogValues(
                RetainedJobType::Completed,
                'job',
            ))->toBe([])
            ->and($catalog->available)->toBeTrue()
            ->and($catalog->message)->toBe(
                '1 retained job could not be inspected and is excluded from exact filters.',
            )
            ->and($client->sortedSets['completed_jobs'] ?? [])
            ->toHaveKey('expired-job')
            ->and($hydrations)->toBe(1);
    });
});
