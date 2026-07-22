<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\RedisQueue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Repositories\RedisJobRepository;
use NckRtl\HorizonNewDawn\Queues\ClearsQueueMetadata;

use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
    config()->set('queue.connections.redis', ['driver' => 'redis']);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('clears a queue through the framework then purges connection-scoped Horizon records', function (): void {
    $trace = new QueueClearOrderTrace;

    $queue = new class($trace) extends RedisQueue
    {
        public ?string $clearedQueue = null;

        public function __construct(private QueueClearOrderTrace $trace) {}

        public function clear($queue = null): int
        {
            $this->trace->push('clear');
            $this->clearedQueue = $queue;

            return 12;
        }
    };

    $manager = new class(app(), $queue) extends QueueManager
    {
        public string|UnitEnum|null $resolvedConnection = null;

        public function __construct($app, private readonly Queue $queue)
        {
            parent::__construct($app);
        }

        public function connection($name = null): Queue
        {
            $this->resolvedConnection = $name;

            return $this->queue;
        }
    };
    app()->instance(QueueManager::class, $manager);

    $jobs = new class extends RedisJobRepository
    {
        public bool $purged = false;

        public function __construct() {}

        public function purge($queue): int
        {
            $this->purged = true;

            return 12;
        }
    };
    app()->instance(JobRepository::class, $jobs);

    $metadata = new class($trace) implements ClearsQueueMetadata
    {
        /** @var array<int, array{0: string, 1: string}> */
        public array $targets = [];

        public function __construct(private QueueClearOrderTrace $trace) {}

        public function purgePending(string $connection, string $queue): int
        {
            $this->trace->push('metadata');
            $this->targets[] = [$connection, $queue];

            return 0;
        }
    };
    app()->instance(ClearsQueueMetadata::class, $metadata);

    delete('/horizon/queues/redis/reports/clear')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 12 jobs from reports.');

    expect($manager->resolvedConnection)->toBe('redis')
        ->and($queue->clearedQueue)->toBe('reports')
        ->and($jobs->purged)->toBeFalse()
        ->and($trace->steps)->toBe(['clear', 'metadata'])
        ->and($metadata->targets)->toBe([['redis', 'reports']]);
});

it('does not call queue-name-only Horizon purge after clearing a connection-scoped queue', function (): void {
    config()->set('queue.connections.sqs', ['driver' => 'sqs']);

    $queue = new class extends RedisQueue
    {
        public function __construct() {}

        public function clear($queue = null): int
        {
            return 3;
        }
    };

    $manager = new class(app(), $queue) extends QueueManager
    {
        public function __construct($app, private readonly Queue $queue)
        {
            parent::__construct($app);
        }

        public function connection($name = null): Queue
        {
            return $this->queue;
        }
    };
    app()->instance(QueueManager::class, $manager);

    $jobs = new class extends RedisJobRepository
    {
        /** @var array<int, string> */
        public array $purgedQueues = [];

        public function __construct() {}

        public function purge($queue): int
        {
            $this->purgedQueues[] = $queue;

            return 1;
        }
    };
    app()->instance(JobRepository::class, $jobs);

    $metadata = new class implements ClearsQueueMetadata
    {
        /** @var array<int, array{0: string, 1: string}> */
        public array $targets = [];

        public function purgePending(string $connection, string $queue): int
        {
            $this->targets[] = [$connection, $queue];

            return 0;
        }
    };
    app()->instance(ClearsQueueMetadata::class, $metadata);

    delete('/horizon/queues/redis/reports/clear')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 3 jobs from reports.');

    // Horizon RedisJobRepository::purge($queue) matches queue name only and would
    // also drop pending/reserved metadata for sqs:reports.
    expect($jobs->purgedQueues)->toBe([])
        ->and($metadata->targets)->toBe([['redis', 'reports']]);
});

it('does not remove Horizon metadata when the driver clear fails', function (): void {
    $queue = new class extends RedisQueue
    {
        public function __construct() {}

        public function clear($queue = null): int
        {
            throw new RuntimeException('driver unavailable');
        }
    };

    $manager = new class(app(), $queue) extends QueueManager
    {
        public function __construct($app, private readonly Queue $queue)
        {
            parent::__construct($app);
        }

        public function connection($name = null): Queue
        {
            return $this->queue;
        }
    };
    app()->instance(QueueManager::class, $manager);

    $jobs = new class extends RedisJobRepository
    {
        public bool $purged = false;

        public function __construct() {}

        public function purge($queue): int
        {
            $this->purged = true;

            return 1;
        }
    };
    app()->instance(JobRepository::class, $jobs);

    $metadata = new class implements ClearsQueueMetadata
    {
        public bool $called = false;

        public function purgePending(string $connection, string $queue): int
        {
            $this->called = true;

            return 0;
        }
    };
    app()->instance(ClearsQueueMetadata::class, $metadata);

    delete('/horizon/queues/redis/reports/clear')
        ->assertRedirect()
        ->assertSessionHas('toast.error', 'Could not clear reports.');

    expect($jobs->purged)->toBeFalse()
        ->and($metadata->called)->toBeFalse();
});

it('honors Horizon authorization', function (): void {
    Horizon::auth(static fn (): bool => false);

    delete('/horizon/queues/redis/reports/clear')->assertForbidden();
});

final class QueueClearOrderTrace
{
    /** @var array<int, string> */
    public array $steps = [];

    public function push(string $step): void
    {
        $this->steps[] = $step;
    }
}
