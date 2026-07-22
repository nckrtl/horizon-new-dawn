<?php

declare(strict_types=1);

use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Jobs\Actions\CancelPendingJob;
use NckRtl\HorizonNewDawn\Jobs\Actions\CancelPendingJobs;
use NckRtl\HorizonNewDawn\Jobs\Actions\ReleaseCancelledJobLocks;
use NckRtl\HorizonNewDawn\Jobs\ForgetsPendingJob;
use NckRtl\HorizonNewDawn\Jobs\PendingJobCancellationResult;
use NckRtl\HorizonNewDawn\Jobs\PendingJobCancellationScope;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardNeverReceives;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('CancelPendingJob', function (): void {
    it('atomically removes a normal pending job from either ready or delayed storage', function (): void {
        $job = horizonJob(0, 'pending-1');
        $job->status = 'pending';
        $job->completed_at = null;
        $job->queue = 'imports';
        $job->payload = json_encode([
            'uuid' => $job->id,
            'displayName' => $job->name,
            'tags' => ['tenant:1'],
            'data' => ['batchId' => null],
        ], JSON_THROW_ON_ERROR);

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $connection = new CancelPendingJobRedisConnectionStub(1);
        $queue = new CancelPendingJobRedisQueueStub($connection);
        $manager = new CancelPendingJobQueueManagerStub(app(), $queue);
        $metadata = new CancelPendingJobMetadataStub;

        $result = (new CancelPendingJob(
            $jobs,
            $manager,
            $metadata,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        ))->handle($job->id);

        expect($result)->toBe(PendingJobCancellationResult::Cancelled)
            ->and($metadata->forgotten)->toBe([[$job->id, ['tenant:1']]])
            ->and($connection->commands)->toHaveCount(1);

        [$method, $arguments] = $connection->commands[0];

        expect($method)->toBe('eval')
            ->and($arguments[0] ?? '')->toContain("redis.call('zrem', KEYS[2], ARGV[1])")
            ->and($arguments[0] ?? '')->toContain("redis.call('lrem', KEYS[1], 1, ARGV[1])")
            ->and($arguments[1] ?? null)->toBe(3)
            ->and($arguments[2] ?? null)->toBe('queues:imports')
            ->and($arguments[3] ?? null)->toBe('queues:imports:delayed')
            ->and($arguments[4] ?? null)->toBe('queues:imports:notify')
            ->and($arguments[5] ?? null)->toBe($job->payload)
            ->and($arguments[6] ?? null)->toBe('1')
            ->and($arguments[7] ?? null)->toBe('1');
    });

    it('uses a shared Redis Cluster hash tag for every queue key', function (
        string $queueName,
        string $expectedReadyKey,
    ): void {
        $job = horizonJob(0, 'pending-1');
        $job->status = 'pending';
        $job->completed_at = null;
        $job->queue = $queueName;

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $connection = new CancelPendingJobRedisConnectionStub(1, true);

        (new CancelPendingJob(
            $jobs,
            new CancelPendingJobQueueManagerStub(
                app(),
                new CancelPendingJobRedisQueueStub($connection),
            ),
            new CancelPendingJobMetadataStub,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        ))->handle($job->id);

        $arguments = $connection->commands[0][1];

        expect($arguments[2] ?? null)->toBe($expectedReadyKey)
            ->and($arguments[3] ?? null)->toBe($expectedReadyKey.':delayed')
            ->and($arguments[4] ?? null)->toBe($expectedReadyKey.':notify');
    })->with([
        'plain queue name' => ['imports', 'queues:{imports}'],
        'existing hash tag' => ['{tenant}:imports', 'queues:{tenant}:imports'],
    ]);

    it('limits queue removal to the requested pending state', function (
        PendingJobCancellationScope $scope,
        string $ready,
        string $delayed,
    ): void {
        $job = horizonJob(0, 'pending-1');
        $job->status = 'pending';
        $job->completed_at = null;

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $connection = new CancelPendingJobRedisConnectionStub(1);

        (new CancelPendingJob(
            $jobs,
            new CancelPendingJobQueueManagerStub(
                app(),
                new CancelPendingJobRedisQueueStub($connection),
            ),
            new CancelPendingJobMetadataStub,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        ))->handle($job->id, $scope);

        $arguments = $connection->commands[0][1];

        expect($arguments[6] ?? null)->toBe($ready)
            ->and($arguments[7] ?? null)->toBe($delayed);
    })->with([
        'ready jobs' => [PendingJobCancellationScope::Ready, '1', '0'],
        'delayed jobs' => [PendingJobCancellationScope::Delayed, '0', '1'],
        'all pending jobs' => [PendingJobCancellationScope::Pending, '1', '1'],
    ]);

    it('cancels all eligible retained jobs without treating reserved or batched jobs as cancellable', function (): void {
        $ready = horizonJob(0, 'ready-1');
        $ready->status = 'pending';
        $ready->completed_at = null;

        $reserved = horizonJob(1, 'reserved-1');
        $reserved->status = 'reserved';
        $reserved->completed_at = null;

        $batched = horizonJob(2, 'batched-1');
        $batched->status = 'pending';
        $batched->completed_at = null;
        $batched->payload = json_encode([
            'uuid' => $batched->id,
            'displayName' => $batched->name,
            'data' => ['batchId' => 'batch-1'],
        ], JSON_THROW_ON_ERROR);

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturns($jobs, 'countPending', 3);
        dashboardReturnsFor($jobs, 'getPending', ['-1'], new Collection([$ready, $reserved, $batched]));
        dashboardReturnsFor($jobs, 'getJobs', [[$ready->id]], new Collection([$ready]));
        dashboardReturnsFor($jobs, 'getJobs', [[$reserved->id]], new Collection([$reserved]));
        dashboardReturnsFor($jobs, 'getJobs', [[$batched->id]], new Collection([$batched]));

        $connection = new CancelPendingJobRedisConnectionStub(1);
        $cancel = new CancelPendingJob(
            $jobs,
            new CancelPendingJobQueueManagerStub(
                app(),
                new CancelPendingJobRedisQueueStub($connection),
            ),
            new CancelPendingJobMetadataStub,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        );

        $result = (new CancelPendingJobs($jobs, $cancel))->handle(PendingJobCancellationScope::Ready);

        expect($result->cancelled)->toBe(1)
            ->and($result->batched)->toBe(1)
            ->and($result->failed)->toBe(0)
            ->and($connection->commands)->toHaveCount(1);
    });

    it('does not clean up Horizon metadata when a worker reserves the job first', function (): void {
        $job = horizonJob(0, 'pending-1');
        $job->status = 'pending';
        $job->completed_at = null;

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $metadata = new CancelPendingJobMetadataStub;

        $result = (new CancelPendingJob(
            $jobs,
            new CancelPendingJobQueueManagerStub(
                app(),
                new CancelPendingJobRedisQueueStub(new CancelPendingJobRedisConnectionStub(0)),
            ),
            $metadata,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        ))->handle($job->id);

        expect($result)->toBe(PendingJobCancellationResult::NotCancellable)
            ->and($metadata->forgotten)->toBe([]);
    });

    it('refuses to cancel reserved jobs', function (): void {
        $job = horizonJob(0, 'reserved-1');
        $job->status = 'reserved';
        $job->completed_at = null;

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $manager = mockDashboardContract(QueueManager::class);
        dashboardNeverReceives($manager, 'connection');
        $result = (new CancelPendingJob(
            $jobs,
            $manager,
            new CancelPendingJobMetadataStub,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        ))->handle($job->id);

        expect($result)->toBe(PendingJobCancellationResult::NotCancellable);
    });

    it('requires batched jobs to use whole-batch cancellation', function (): void {
        $job = horizonJob(0, 'batched-1');
        $job->status = 'pending';
        $job->completed_at = null;
        $job->payload = json_encode([
            'uuid' => $job->id,
            'displayName' => $job->name,
            'data' => ['batchId' => 'batch-1'],
        ], JSON_THROW_ON_ERROR);

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));

        $manager = mockDashboardContract(QueueManager::class);
        dashboardNeverReceives($manager, 'connection');

        $result = (new CancelPendingJob(
            $jobs,
            $manager,
            new CancelPendingJobMetadataStub,
            new ReleaseCancelledJobLocks(app(CacheFactory::class), app(Encrypter::class)),
        ))->handle($job->id);

        expect($result)->toBe(PendingJobCancellationResult::Batched);
    });
});

describe('ReleaseCancelledJobLocks', function (): void {
    it('releases a unique job lock from Laravel queue context', function (): void {
        $job = new CancelPendingUniqueJob('customer-42');
        $cache = app(CacheFactory::class);
        $lock = new UniqueLock($cache->store('array'));

        expect($lock->acquire($job))->toBeTrue();

        (new ReleaseCancelledJobLocks($cache, app(Encrypter::class)))->handle([
            'illuminate:log:context' => [
                'hidden' => [
                    'laravel_unique_job_cache_store' => serialize('array'),
                    'laravel_unique_job_key' => serialize(UniqueLock::getKey($job)),
                ],
            ],
        ]);

        expect($lock->acquire($job))->toBeTrue();
    });
});

final class CancelPendingJobQueueManagerStub extends QueueManager
{
    public function __construct($app, private readonly Queue $queue)
    {
        parent::__construct($app);
    }

    public function connection($name = null): Queue
    {
        return $this->queue;
    }
}

final class CancelPendingJobRedisQueueStub extends RedisQueue
{
    public function __construct(private readonly Connection $redisConnection) {}

    public function getConnection(): Connection
    {
        return $this->redisConnection;
    }
}

final class CancelPendingJobRedisConnectionStub extends Connection
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $commands = [];

    public function __construct(
        private readonly int $result,
        private readonly bool $cluster = false,
    ) {}

    public function isCluster(): bool
    {
        return $this->cluster;
    }

    /** @param array<int, string>|string $channels */
    public function createSubscription($channels, $callback, $method = 'subscribe'): void {}

    /** @param array<int, mixed> $parameters */
    public function command($method, array $parameters = []): int
    {
        $this->commands[] = [$method, $parameters];

        return $this->result;
    }
}

final class CancelPendingJobMetadataStub implements ForgetsPendingJob
{
    /** @var array<int, array{0: string, 1: array<int, string>}> */
    public array $forgotten = [];

    public function forgetPending(string $id, array $tags): bool
    {
        $this->forgotten[] = [$id, $tags];

        return true;
    }
}

final readonly class CancelPendingUniqueJob implements ShouldBeUnique
{
    public function __construct(public string $customerId) {}

    public function uniqueId(): string
    {
        return $this->customerId;
    }
}
