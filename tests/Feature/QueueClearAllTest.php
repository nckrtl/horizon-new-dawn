<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\RedisQueue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Horizon;
use NckRtl\HorizonNewDawn\Queues\ClearsQueueMetadata;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('clears every supervised queue through the framework', function (): void {
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturns($supervisors, 'all', [
        (object) ['processes' => ['redis:reports,batches' => 2]],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturns($jobs, 'countPending', 0);
    app()->instance(JobRepository::class, $jobs);

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

    delete('/horizon/queues')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 7 jobs from all queues.');

    expect($queue->cleared)->toEqualCanonicalizing(['reports', 'batches'])
        ->and($metadata->targets)->toEqualCanonicalizing([
            ['redis', 'reports'],
            ['redis', 'batches'],
        ]);
});

it('honors Horizon authorization when clearing all queues', function (): void {
    Horizon::auth(static fn (): bool => false);

    delete('/horizon/queues')->assertForbidden();
});
