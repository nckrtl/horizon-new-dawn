<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\RedisQueue;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\from;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('makes a delayed job available to workers immediately', function (): void {
    bindDelayedJobRelease(1);

    from('/horizon/jobs/pending/delayed-1')
        ->post('/horizon/jobs/pending/delayed-1/release')
        ->assertRedirect('/horizon/jobs/pending/delayed-1')
        ->assertSessionHas('toast.success', 'Job is now available to workers.');
});

it('reports when the job is no longer delayed', function (): void {
    bindDelayedJobRelease(0);

    from('/horizon/jobs/pending/delayed-1')
        ->post('/horizon/jobs/pending/delayed-1/release')
        ->assertRedirect('/horizon/jobs/pending/delayed-1')
        ->assertSessionHas('toast.error', 'This job is no longer delayed.');
});

it('honors Horizon authorization when releasing a delayed job', function (): void {
    Horizon::auth(static fn (): bool => false);

    post('/horizon/jobs/pending/delayed-1/release')->assertForbidden();
});

function bindDelayedJobRelease(int $redisResult): void
{
    $job = horizonJob(0, 'delayed-1');
    $job->status = 'pending';

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'getJobs', [[$job->id]], new Collection([$job]));
    app()->instance(JobRepository::class, $jobs);

    app()->instance(QueueManager::class, new DelayedJobReleaseQueueManager(
        app(),
        new DelayedJobReleaseRedisQueue(new DelayedJobReleaseRedisConnection($redisResult)),
    ));
}

final class DelayedJobReleaseQueueManager extends QueueManager
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

final class DelayedJobReleaseRedisQueue extends RedisQueue
{
    public function __construct(private readonly Connection $redisConnection) {}

    public function getConnection(): Connection
    {
        return $this->redisConnection;
    }

    public function getQueue($queue): string
    {
        return 'queues:'.($queue ?: 'default');
    }

    /** @return array<int, string> */
    public function migrateExpiredJobs($from, $to): array
    {
        return [];
    }
}

final class DelayedJobReleaseRedisConnection extends Connection
{
    public function __construct(private readonly int $result) {}

    /** @param array<int, string>|string $channels */
    public function createSubscription($channels, $callback, $method = 'subscribe'): void {}

    /** @param array<int, mixed> $parameters */
    public function command($method, array $parameters = []): int
    {
        return $this->result;
    }
}
