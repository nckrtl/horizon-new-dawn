<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Horizon;
use NckRtl\HorizonNewDawn\Queues\ClearsQueueMetadata;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countPending', 0);
    app()->instance(JobRepository::class, $jobs);
});

it('clears retained pending queues that are no longer supervised', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:reports' => 1]],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $supervised = horizonJob(0, 'pending-supervised');
    $supervised->status = 'pending';
    $supervised->queue = 'reports';

    $unsupervised = horizonJob(1, 'pending-unsupervised');
    $unsupervised->status = 'pending';
    $unsupervised->connection = 'archive';
    $unsupervised->queue = 'orphaned';

    $ineligible = horizonJob(2, 'completed-stale-reference');
    $ineligible->connection = 'archive';
    $ineligible->queue = 'completed';

    $nextPage = horizonJob(50, 'pending-next-page');
    $nextPage->status = 'reserved';
    $nextPage->queue = 'delayed';

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countPending', 51);
    dashboardReturnsFor($jobs, 'getPending', ['-1'], new Collection([
        $supervised,
        $unsupervised,
        $ineligible,
    ]));
    dashboardReturnsFor($jobs, 'getPending', ['49'], new Collection([$nextPage]));
    app()->instance(JobRepository::class, $jobs);

    $manager = new class(app()) extends QueueManager
    {
        /** @var array<int, array{0: string, 1: string}> */
        public array $cleared = [];

        public function connection($name = null): Queue
        {
            $connection = is_string($name) ? $name : '';

            return new class(fn (string $queue): int => $this->record($connection, $queue)) extends RedisQueue
            {
                public function __construct(private readonly Closure $record) {}

                public function clear($queue = null): int
                {
                    return ($this->record)((string) $queue);
                }
            };
        }

        private function record(string $connection, string $queue): int
        {
            $this->cleared[] = [$connection, $queue];

            return 1;
        }
    };
    app()->instance(QueueManager::class, $manager);

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

    delete('/horizon/jobs/pending')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 3 pending jobs.');

    $expectedTargets = [
        ['redis', 'reports'],
        ['archive', 'orphaned'],
        ['redis', 'delayed'],
    ];

    expect($manager->cleared)->toBe($expectedTargets)
        ->and($metadata->targets)->toBe($expectedTargets);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('clears every supervised pending queue through the framework', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:reports,batches' => 2]],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $queue = new class extends RedisQueue
    {
        /** @var array<int, string> */
        public array $cleared = [];

        public function __construct() {}

        public function clear($queue = null): int
        {
            $this->cleared[] = (string) $queue;

            return $queue === 'reports' ? 4 : 3;
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

    delete('/horizon/jobs/pending')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 7 pending jobs.');

    expect($queue->cleared)->toEqualCanonicalizing(['reports', 'batches'])
        ->and($metadata->targets)->toEqualCanonicalizing([
            ['redis', 'reports'],
            ['redis', 'batches'],
        ]);
});

it('reports targets that could not be cleared without skipping supported queues', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:reports' => 1, 'sqs:exports' => 1]],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $redis = new class extends RedisQueue
    {
        public function __construct() {}

        public function clear($queue = null): int
        {
            return 2;
        }
    };
    $unsupported = mockDashboardContract(Queue::class);

    $manager = new class(app(), $redis, $unsupported) extends QueueManager
    {
        public function __construct(
            $app,
            private readonly Queue $redis,
            private readonly Queue $unsupported,
        ) {
            parent::__construct($app);
        }

        public function connection($name = null): Queue
        {
            return $name === 'redis' ? $this->redis : $this->unsupported;
        }
    };
    app()->instance(QueueManager::class, $manager);

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

    delete('/horizon/jobs/pending')
        ->assertRedirect()
        ->assertSessionHas('toast.error', 'Cleared 2 pending jobs, but could not clear sqs:exports.');

    expect($metadata->targets)->toBe([['redis', 'reports']]);
});

it('honors Horizon authorization for clearing all pending jobs', function (): void {
    Horizon::auth(static fn (): bool => false);

    delete('/horizon/jobs/pending')->assertForbidden();
});
