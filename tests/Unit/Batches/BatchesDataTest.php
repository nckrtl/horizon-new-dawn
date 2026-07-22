<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Laravel\Horizon\Contracts\JobRepository;
use Mockery\CompositeExpectation;
use Mockery\Expectation;
use Mockery\MockInterface;
use NckRtl\HorizonNewDawn\Batches\BatchCreatedRange;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Tests\Support\HorizonJob;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('BatchesData', function (): void {
    it('exposes normalized batch rows and explicit queue attribution', function (): void {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', 'default');

        $repository = mockDashboardContract(BatchRepository::class);
        $jobs = mockDashboardContract(JobRepository::class);
        $batch = horizonBatch(
            'batch-1',
            totalJobs: 10,
            pendingJobs: 3,
            failedJobs: 1,
        );
        $data = batchData($repository, $jobs);

        expect($data->row($batch)->toArray())->toMatchArray([
            'id' => 'batch-1',
            'connection' => 'redis',
            'queue' => 'imports',
            'pendingJobs' => 3,
            'failedJobs' => 1,
            'processedJobs' => 7,
            'progress' => 70,
            'status' => 'failures',
        ])->and($data->queue($batch))->toBe('imports');

        $batch->options['queue'] = '';

        expect($data->queue($batch))->toBe('default');
    });

    it('resolves effective batch attribution from explicit options and queue configuration', function (): void {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', 'emails');
        config()->set('queue.connections.sqs.queue', 'notifications');

        $repository = mockDashboardContract(BatchRepository::class);
        $jobs = mockDashboardContract(JobRepository::class);
        $data = batchData($repository, $jobs);

        $implicit = horizonBatch('implicit');
        $implicit->options = [];

        $connectionOnly = horizonBatch('connection-only');
        $connectionOnly->options = ['connection' => 'sqs'];

        $missingConfiguredQueue = horizonBatch('fallback');
        $missingConfiguredQueue->options = ['connection' => 'database'];

        $explicit = horizonBatch('explicit');
        $explicit->options = ['connection' => 'sqs', 'queue' => 'priority'];

        expect($data->row($implicit)->toArray())->toMatchArray([
            'connection' => 'redis',
            'queue' => 'emails',
        ])->and($data->row($connectionOnly)->toArray())->toMatchArray([
            'connection' => 'sqs',
            'queue' => 'notifications',
        ])->and($data->row($missingConfiguredQueue)->toArray())->toMatchArray([
            'connection' => 'database',
            'queue' => 'default',
        ])->and($data->row($explicit)->toArray())->toMatchArray([
            'connection' => 'sqs',
            'queue' => 'priority',
        ]);
    });

    it('normalizes pending failure finished cancelled and unnamed batches', function (): void {
        $repository = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($repository, 'get', [50, null], [
            horizonBatch('pending', totalJobs: 100, pendingJobs: 25),
            horizonBatch('failures', totalJobs: 100, pendingJobs: 10, failedJobs: 3),
            horizonBatch('finished', totalJobs: 100, pendingJobs: 0, failedJobs: 3, finishedAt: 1_784_281_100),
            horizonBatch('cancelled', name: '', totalJobs: 100, pendingJobs: 40, cancelledAt: 1_784_281_050),
        ]);
        $jobs = mockDashboardContract(JobRepository::class);

        $page = batchData($repository, $jobs)->page(null, null);

        expect($page->available)->toBeTrue()
            ->and(array_map(static fn ($batch): string => $batch->status, $page->batches))
            ->toBe(['pending', 'failures', 'finished', 'cancelled'])
            ->and(array_map(static fn ($batch): int => $batch->progress, $page->batches))
            ->toBe([75, 90, 100, 60])
            ->and($page->batches[3]->displayName)->toBe('cancelled')
            ->and($page->batches[2]->processedJobs)->toBe(100);
    });

    it('exposes the last descending id as its next scroll cursor', function (): void {
        $repository = mockDashboardContract(BatchRepository::class);
        $batches = array_map(
            static fn (int $index) => horizonBatch("batch-{$index}"),
            range(50, 1),
        );
        dashboardReturnsFor($repository, 'get', [50, 'batch-51'], $batches);
        $jobs = mockDashboardContract(JobRepository::class);

        $page = batchData($repository, $jobs)->page('batch-51', null);

        expect($page->batches)->toHaveCount(50)
            ->and($page->current)->toBe('batch-51')
            ->and($page->next)->toBe('batch-1');
    });

    it('returns an explicit unavailable page when the batch database fails', function (): void {
        $repository = mockDashboardContract(BatchRepository::class);
        dashboardThrows($repository, 'get', new RuntimeException('database password leaked'));
        $jobs = mockDashboardContract(JobRepository::class);

        $page = batchData($repository, $jobs)->page(null, null);

        expect($page->available)->toBeFalse()
            ->and($page->batches)->toBe([])
            ->and($page->message)->toBe('Batches are currently unavailable.')
            ->and($page->message)->not->toContain('password');
    });

    it('returns a safe batch detail with retained jobs for every batch status', function (): void {
        $batch = horizonBatch(
            'batch-1',
            totalJobs: 5,
            pendingJobs: 2,
            failedJobs: 1,
            failedJobIds: ['failed-1'],
        );
        $repository = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($repository, 'find', ['batch-1'], $batch);
        $pending = batchDataJob(0, 'pending-1', 'batch-1', 'pending');
        $completed = batchDataJob(1, 'completed-1', 'batch-1', 'completed');
        $completedTwo = batchDataJob(2, 'completed-2', 'batch-1', 'completed');
        $completedThree = batchDataJob(3, 'completed-3', 'batch-1', 'completed');
        $failed = batchDataJob(4, 'failed-1', 'batch-1', 'failed');
        $failed->status = 'failed';
        $failed->completed_at = null;
        $failed->failed_at = '1784281004.25';
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getPending', [null], new Collection([$pending]));
        dashboardReturnsFor(
            $jobs,
            'getCompleted',
            [null],
            new Collection([$completed, $completedTwo, $completedThree]),
        );
        dashboardReturnsFor($jobs, 'getJobs', [['failed-1']], new Collection([$failed]));

        $detail = batchData($repository, $jobs)->find('batch-1');

        expect($detail)->not->toBeNull()
            ->and($detail?->id)->toBe('batch-1')
            ->and($detail?->connection)->toBe('redis')
            ->and($detail?->queue)->toBe('imports')
            ->and($detail?->jobs->pending->total)->toBe(1)
            ->and($detail?->jobs->pending->rows[0]->id)->toBe('pending-1')
            ->and($detail?->jobs->completed->total)->toBe(3)
            ->and($detail?->jobs->completed->rows[0]->id)->toBe('completed-1')
            ->and($detail?->jobs->failed->total)->toBe(1)
            ->and($detail?->jobs->failed->rows[0]->id)->toBe('failed-1')
            ->and($detail?->jobs->failed->rows[0]->toArray())->not->toHaveKeys(['payload', 'exception', 'context']);
    });

    it('returns null for a missing batch', function (): void {
        $repository = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($repository, 'find', ['missing'], null);
        $jobs = mockDashboardContract(JobRepository::class);

        expect(batchData($repository, $jobs)->find('missing'))->toBeNull();
    });

    it('escapes percent and underscore search wildcards', function (): void {
        config()->set('queue.batching.database', null);
        $repository = mockDashboardContract(BatchRepository::class);
        $jobs = mockDashboardContract(JobRepository::class);
        $resolver = mockDashboardContract(ConnectionResolverInterface::class);
        $connection = mockDashboardContract(ConnectionInterface::class);
        $builder = mockDashboardContract(Builder::class);
        $nested = mockDashboardContract(Builder::class);

        dashboardReturnsFor($resolver, 'connection', [null], $connection);
        dashboardReturnsFor($connection, 'table', ['job_batches'], $builder);
        dashboardReturnsFor($nested, 'where', ['name', 'like', '%100\%\_done%'], $nested);
        dashboardReturnsFor($nested, 'orWhere', ['id', 'like', '%100\%\_done%'], $nested);
        batchReturnsForClosure(
            $builder,
            'where',
            function (Closure $scope) use ($builder, $nested) {
                $scope($nested);

                return $builder;
            },
        );
        dashboardReturnsFor($builder, 'orderByDesc', ['id'], $builder);
        dashboardReturnsFor($builder, 'limit', [50], $builder);
        dashboardReturnsFor($builder, 'pluck', ['id'], collect());

        $data = new BatchesData(
            $repository,
            new BatchJobsData($jobs, new JobsData($jobs)),
            $resolver,
        );

        expect($data->search('100%_done', null))->toBe([]);
    });

    it('filters queue connection and created range before returning a page', function (): void {
        Date::setTestNow('2026-07-21 15:00:00');
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', 'imports');

        $matching = horizonBatch('matching');
        $matching->options = [];
        $matching->createdAt = Date::now()->subDays(2)->toImmutable();

        $wrongQueue = horizonBatch('wrong-queue');
        $wrongQueue->options['queue'] = 'reports';
        $wrongQueue->createdAt = Date::now()->subDays(2)->toImmutable();

        $wrongConnection = horizonBatch('wrong-connection');
        $wrongConnection->options['connection'] = 'database';
        $wrongConnection->createdAt = Date::now()->subDays(2)->toImmutable();

        $tooOld = horizonBatch('too-old');
        $tooOld->createdAt = Date::now()->subDays(8)->toImmutable();

        $repository = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($repository, 'get', [50, null], [
            $matching,
            $wrongQueue,
            $wrongConnection,
            $tooOld,
        ]);
        $jobs = mockDashboardContract(JobRepository::class);

        $page = batchData($repository, $jobs)->page(
            null,
            null,
            'imports',
            'redis',
            BatchCreatedRange::Last7Days,
        );

        expect($page->batches)->toHaveCount(1)
            ->and($page->batches[0]->id)->toBe('matching')
            ->and($page->next)->toBeNull();

        Date::setTestNow();
    });

    it('maps every supported created range to the requested cutoff', function (): void {
        Date::setTestNow('2026-07-21 15:00:00');

        expect([
            BatchCreatedRange::LastHour->cutoffTimestamp(),
            BatchCreatedRange::Last24Hours->cutoffTimestamp(),
            BatchCreatedRange::Last7Days->cutoffTimestamp(),
            BatchCreatedRange::Last30Days->cutoffTimestamp(),
        ])->toBe([
            Date::now()->subHour()->getTimestamp(),
            Date::now()->subHours(24)->getTimestamp(),
            Date::now()->subDays(7)->getTimestamp(),
            Date::now()->subDays(30)->getTimestamp(),
        ]);

        Date::setTestNow();
    });

    it('counts only finished and cancelled batches as clearable', function (): void {
        config()->set('queue.batching.database', null);
        config()->set('queue.batching.table', 'job_batches');
        $repository = mockDashboardContract(BatchRepository::class);
        $jobs = mockDashboardContract(JobRepository::class);
        $resolver = mockDashboardContract(ConnectionResolverInterface::class);
        $connection = mockDashboardContract(ConnectionInterface::class);
        $builder = mockDashboardContract(Builder::class);

        dashboardReturnsFor($resolver, 'connection', [null], $connection);
        dashboardReturnsFor($connection, 'table', ['job_batches'], $builder);
        dashboardReturnsFor($builder, 'whereNotNull', ['finished_at'], $builder);
        dashboardReturnsFor($builder, 'orWhereNotNull', ['cancelled_at'], $builder);
        dashboardReturns($builder, 'count', 3);

        $data = new BatchesData(
            $repository,
            new BatchJobsData($jobs, new JobsData($jobs)),
            $resolver,
        );

        expect($data->finishedCount())->toBe(3);
    });
});

function batchData(
    BatchRepository&MockInterface $batches,
    JobRepository&MockInterface $jobs,
): BatchesData {
    return new BatchesData(
        $batches,
        new BatchJobsData($jobs, new JobsData($jobs)),
        app(ConnectionResolverInterface::class),
    );
}

function batchDataJob(int $index, string $id, string $batchId, string $status): HorizonJob
{
    $job = horizonJob($index, $id);
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['data']['batchId'] = $batchId;
    $job->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    $job->status = $status;

    return $job;
}

function batchReturnsForClosure(MockInterface $mock, string $method, Closure $return): void
{
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation
            ->with(Mockery::type(Closure::class))
            ->once()
            ->andReturnUsing($return);

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('with', [Mockery::type(Closure::class)]);
        $expectation->__call('once', []);
        $expectation->__call('andReturnUsing', [$return]);

        return;
    }

    throw new LogicException("Unable to configure {$method} expectation.");
}
