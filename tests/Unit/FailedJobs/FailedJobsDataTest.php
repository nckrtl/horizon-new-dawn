<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsUsing;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('FailedJobsData', function (): void {
    it('finds retryable jobs beyond the first failed-job page', function (): void {
        $ineligible = [];

        for ($index = 0; $index < 50; $index++) {
            $job = horizonJob($index, "failed-{$index}");
            $job->retried_by = json_encode([
                ['id' => "retry-{$index}", 'status' => 'completed'],
            ], JSON_THROW_ON_ERROR);
            $ineligible[] = $job;
        }

        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getFailed', ['-1'], new Collection($ineligible));
        dashboardReturnsFor($repository, 'getFailed', ['49'], new Collection([
            horizonJob(50, 'retryable'),
        ]));

        $data = new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
        );

        expect($data->hasRetryable())->toBeTrue();
    });

    it('reports no retryable jobs when every retained failure has already been retried', function (): void {
        $job = horizonJob(0, 'failed-1');
        $job->retried_by = json_encode([
            ['id' => 'retry-1', 'status' => 'completed'],
        ], JSON_THROW_ON_ERROR);

        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getFailed', ['-1'], new Collection([$job]));

        $data = new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
        );

        expect($data->hasRetryable())->toBeFalse();
    });

    it('finds retryable jobs after an expired hash at a raw failed-job boundary', function (): void {
        $ids = array_map(
            static fn (int $index): string => "failed-{$index}",
            range(0, 50),
        );
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getFailed', new Collection(array_map(
            static function (int $index): object {
                $job = horizonJob($index, "failed-{$index}");
                $job->retried_by = json_encode([
                    ['id' => "retry-{$index}", 'status' => 'completed'],
                ], JSON_THROW_ON_ERROR);

                return $job;
            },
            range(0, 48),
        )));
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $requestedIds, int $startingAt = 0): Collection {
                $jobs = [];

                foreach ($requestedIds as $offset => $id) {
                    if ($id === 'failed-20') {
                        continue;
                    }

                    $job = horizonJob($startingAt + $offset, $id);

                    if ($id !== 'failed-50') {
                        $job->retried_by = json_encode([
                            ['id' => "retry-{$id}", 'status' => 'completed'],
                        ], JSON_THROW_ON_ERROR);
                    }

                    $jobs[] = $job;
                }

                return new Collection($jobs);
            },
        );

        $connection = mockDashboardContract(Connection::class);
        dashboardReturnsUsing(
            $connection,
            'zrevrange',
            static fn (string $key, int $start, int $stop): array => array_slice(
                $ids,
                $start,
                $stop - $start + 1,
            ),
        );
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $connection);

        $data = new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
            $redis,
        );

        expect($data->hasRetryable())->toBeTrue();
    });

    it('normalizes a failed row with its retry metadata', function (): void {
        $job = horizonJob(0, 'failed-1');
        $job->retried_by = json_encode([
            ['id' => 'retry-1', 'status' => 'completed', 'retried_at' => 100],
        ], JSON_THROW_ON_ERROR);

        $repository = mockDashboardContract(JobRepository::class);
        $data = new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
        );

        $row = $data->row($job);

        expect($row?->retried)->toBeTrue()
            ->and($row?->retryCompleted)->toBeTrue()
            ->and($row?->retryCount)->toBe(1)
            ->and($row?->latestRetryStatus)->toBe('completed')
            ->and($row?->retryEligible)->toBeFalse();
    });

    it('uses the shared retry policy for failed list rows', function (): void {
        $fresh = horizonJob(0, 'fresh');
        $allFailed = horizonJob(1, 'all-failed');
        $allFailed->retried_by = json_encode([
            ['id' => 'retry-1', 'status' => 'failed'],
            ['id' => 'retry-2', 'status' => 'failed'],
        ], JSON_THROW_ON_ERROR);
        $pending = horizonJob(2, 'pending');
        $pending->retried_by = json_encode([
            ['id' => 'retry-3', 'status' => 'pending'],
        ], JSON_THROW_ON_ERROR);
        $retryChild = horizonJob(3, 'retry-child');
        $payload = json_decode($retryChild->payload, true, flags: JSON_THROW_ON_ERROR);
        $retryChild->payload = json_encode([...$payload, 'retry_of' => 'fresh'], JSON_THROW_ON_ERROR);

        $repository = mockDashboardContract(JobRepository::class);
        $data = new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
        );

        expect($data->row($fresh)?->retryEligible)->toBeTrue()
            ->and($data->row($allFailed)?->retryEligible)->toBeTrue()
            ->and($data->row($pending)?->retryEligible)->toBeFalse()
            ->and($data->row($retryChild)?->retryEligible)->toBeTrue();
    });

    it('returns safe failed rows with their retry state', function (): void {
        $job = horizonJob(0, 'failed-1');
        $job->status = 'failed';
        $job->failed_at = '1784281003.5';
        $job->retried_by = json_encode([
            ['id' => 'retry-1', 'status' => 'completed', 'retried_at' => 1_784_281_100],
        ], JSON_THROW_ON_ERROR);

        $repository = mockDashboardContract(JobRepository::class);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($repository, 'getFailed', new Collection([$job]));
        dashboardReturns($repository, 'countFailed', 1);

        $page = (new FailedJobsData(
            $repository,
            $tags,
            new JobsData($repository),
            new FailedJobRetryEligibility,
        ))->page(-1);
        $row = $page->items[0]->toArray();

        expect($page->available)->toBeTrue()
            ->and($page->total)->toBe(1)
            ->and($row['id'])->toBe('failed-1')
            ->and($row['attempts'])->toBe(0)
            ->and($row['retryOf'])->toBeNull()
            ->and($row['retried'])->toBeTrue()
            ->and($row['retryCompleted'])->toBeTrue()
            ->and($row['retryCount'])->toBe(1)
            ->and($row['latestRetryStatus'])->toBe('completed')
            ->and($row['retryEligible'])->toBeFalse()
            ->and($row)->not->toHaveKeys(['payload', 'exception', 'context']);
    });

    it('paginates failed jobs through the full Horizon tag repository', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($tags, 'paginate', ['failed:tenant:42', 50, 51], [
            50 => 'failed-51',
        ]);
        dashboardReturnsFor($tags, 'count', ['failed:tenant:42'], 51);
        dashboardReturnsFor($repository, 'getJobs', [['failed-51'], 50], new Collection([
            horizonJob(50, 'failed-51'),
        ]));

        $page = (new FailedJobsData(
            $repository,
            $tags,
            new JobsData($repository),
            new FailedJobRetryEligibility,
        ))
            ->page(50, 'tenant:42');

        expect($page->items[0]->id)->toBe('failed-51')
            ->and($page->total)->toBe(51)
            ->and($page->current)->toBe(50)
            ->and($page->next)->toBeNull();
    });

    it('reads failed jobs from oldest to newest', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'countFailed', 2);
        dashboardReturnsFor($repository, 'getJobs', [['oldest', 'newest'], 0], new Collection([
            horizonJob(0, 'oldest'),
            horizonJob(1, 'newest'),
        ]));

        $connection = mockDashboardContract(Connection::class);
        dashboardReturnsFor($connection, 'zrevrange', ['failed_jobs', 0, 50], ['oldest', 'newest']);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $connection);

        $page = (new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
            $redis,
        ))->page(-1);

        expect(array_column($page->items, 'id'))->toBe(['oldest', 'newest']);
    });

    it('continues from the raw Redis boundary when a failed job hash has expired', function (): void {
        $ids = array_map(
            static fn (int $index): string => "failed-{$index}",
            range(0, 50),
        );
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'countFailed', count($ids));
        dashboardReturnsUsing(
            $repository,
            'getJobs',
            static function (array $requestedIds, int $startingAt = 0): Collection {
                $jobs = [];

                foreach ($requestedIds as $offset => $id) {
                    if ($id === 'failed-20') {
                        continue;
                    }

                    $jobs[] = horizonJob($startingAt + $offset, $id);
                }

                return new Collection($jobs);
            },
        );

        $connection = mockDashboardContract(Connection::class);
        dashboardReturnsUsing(
            $connection,
            'zrevrange',
            static fn (string $key, int $start, int $stop): array => array_slice(
                $ids,
                $start,
                $stop - $start + 1,
            ),
        );
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $connection);

        $page = (new FailedJobsData(
            $repository,
            mockDashboardContract(TagRepository::class),
            new JobsData($repository),
            new FailedJobRetryEligibility,
            $redis,
        ))->page(-1);

        expect($page->items)->toHaveCount(49)
            ->and($page->next)->toBe(49);
    });

    it('reads tag-filtered failed jobs from oldest to newest', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'getJobs', [['oldest', 'newest'], 0], new Collection([
            horizonJob(0, 'oldest'),
            horizonJob(1, 'newest'),
        ]));

        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($tags, 'count', ['failed:tenant:42'], 2);
        $connection = mockDashboardContract(Connection::class);
        dashboardReturnsFor($connection, 'zrange', ['failed:tenant:42', 0, 50], ['oldest', 'newest']);
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $connection);

        $page = (new FailedJobsData(
            $repository,
            $tags,
            new JobsData($repository),
            new FailedJobRetryEligibility,
            $redis,
        ))->page(0, 'tenant:42');

        expect(array_column($page->items, 'id'))->toBe(['oldest', 'newest']);
    });

    it('returns a failed detail with safe payload context and exception text', function (): void {
        $job = horizonJob(0, 'failed-1');
        $job->status = 'failed';
        $job->failed_at = '1784281003.5';
        $job->context = json_encode(['tenant' => 42, 'attempt' => 3], JSON_THROW_ON_ERROR);
        $job->exception = "RuntimeException: Import failed\n#0 /app/Import.php:12";
        $job->retried_by = json_encode([
            ['id' => 'retry-1', 'status' => 'completed', 'retried_at' => 1_784_281_100],
        ], JSON_THROW_ON_ERROR);

        $repository = mockDashboardContract(JobRepository::class);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($repository, 'findFailed', ['failed-1'], $job);

        $detail = (new FailedJobsData(
            $repository,
            $tags,
            new JobsData($repository),
            new FailedJobRetryEligibility,
        ))->find('failed-1');

        expect($detail)->not->toBeNull();

        if ($detail === null) {
            throw new LogicException('Expected a normalized failed job detail.');
        }

        $data = $detail->payload['data'] ?? null;

        expect($detail->payload['displayName'] ?? null)->toBe('App\\Jobs\\ImportFeed')
            ->and($data)->toBeArray()
            ->and($data)->not->toHaveKey('command')
            ->and($detail->context)->toBe(['tenant' => 42, 'attempt' => 3])
            ->and($detail->exception)->toContain('RuntimeException: Import failed')
            ->and($detail->retryEligible)->toBeFalse()
            ->and(array_map(
                static fn ($retry): array => $retry->toArray(),
                $detail->retriedBy,
            ))->toBe([
                ['id' => 'retry-1', 'status' => 'completed', 'retriedAt' => 1_784_281_100.0],
            ]);
    });

    it('returns null when a failed job no longer exists', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturnsFor($repository, 'findFailed', ['missing'], null);

        expect((new FailedJobsData(
            $repository,
            $tags,
            new JobsData($repository),
            new FailedJobRetryEligibility,
        ))->find('missing'))->toBeNull();
    });
});
