<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

describe('batch pages', function (): void {
    it('renders the batch index and detail through dedicated routes', function (): void {
        $batch = horizonBatch(
            'batch-1',
            name: 'Import customer records',
            totalJobs: 5,
            pendingJobs: 2,
            failedJobs: 1,
            failedJobIds: ['failed-1'],
        );
        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'get', [50, null], [$batch]);
        dashboardReturnsFor($batches, 'get', [49, 'batch-1'], []);
        dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getPending', [null], new Collection([
            batchFeatureJob(0, 'pending-1', 'batch-1', 'pending'),
        ]));
        dashboardReturnsFor($jobs, 'getCompleted', [null], new Collection([
            batchFeatureJob(1, 'completed-1', 'batch-1', 'completed'),
            batchFeatureJob(2, 'completed-2', 'batch-1', 'completed'),
            batchFeatureJob(3, 'completed-3', 'batch-1', 'completed'),
        ]));
        dashboardReturnsFor($jobs, 'getJobs', [['failed-1']], new Collection([
            batchFeatureJob(4, 'failed-1', 'batch-1', 'failed'),
        ]));
        app()->instance(BatchesData::class, new BatchesData(
            $batches,
            new BatchJobsData($jobs, new JobsData($jobs)),
        ));

        get('/horizon/batches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Batches/Index')
                ->where('meta.title', 'Batches')
                ->where('meta.activeNavigation', 'batches')
                ->where('query', '')
                ->where('batches.data.0.id', 'batch-1')
                ->where('batches.data.0.progress', 60));

        get('/horizon/batches/batch-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Batches/Show')
                ->where('meta.title', 'Import customer records')
                ->where('batch.id', 'batch-1')
                ->where('batch.jobs.pending.total', 1)
                ->where('batch.jobs.pending.rows.0.id', 'pending-1')
                ->where('batch.jobs.completed.total', 3)
                ->where('batch.jobs.completed.rows.0.id', 'completed-1')
                ->where('batch.jobs.failed.total', 1)
                ->where('batch.jobs.failed.rows.0.id', 'failed-1'));
    });

    it('returns 404 when a batch no longer exists', function (): void {
        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'find', ['missing'], null);
        $jobs = mockDashboardContract(JobRepository::class);
        app()->instance(BatchesData::class, new BatchesData(
            $batches,
            new BatchJobsData($jobs, new JobsData($jobs)),
        ));

        get('/horizon/batches/missing')->assertNotFound();
    });

    it('applies and exposes batch index filters from the query string', function (): void {
        Date::setTestNow('2026-07-21 15:00:00');

        $batch = horizonBatch('batch-1');
        $batch->createdAt = Date::now()->subHours(2)->toImmutable();
        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'get', [50, null], [$batch]);
        dashboardReturnsFor($batches, 'get', [50, 'batch-1'], []);
        $jobs = mockDashboardContract(JobRepository::class);
        app()->instance(BatchesData::class, new BatchesData(
            $batches,
            new BatchJobsData($jobs, new JobsData($jobs)),
        ));

        get('/horizon/batches?queue=imports&connection=redis&created=day')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('filters.queue', 'imports')
                ->where('filters.connection', 'redis')
                ->where('filters.created', 'day')
                ->where('batches.data.0.id', 'batch-1')
                ->where('batches.data.0.queue', 'imports')
                ->where('batches.data.0.connection', 'redis'));

        Date::setTestNow();
    });

    it('rejects unsupported batch created ranges', function (): void {
        get('/horizon/batches?created=forever')
            ->assertRedirect()
            ->assertSessionHasErrors('created');
    });

    it('retries failed batch jobs with feedback and honors Horizon authorization', function (): void {
        Bus::fake();

        $batch = horizonBatch('batch-1', failedJobs: 1, failedJobIds: ['failed-1']);
        $batches = mockDashboardContract(BatchRepository::class);
        dashboardReturnsFor($batches, 'find', ['batch-1'], $batch);
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'getJobs', [['failed-1']], new Collection([horizonJob(0, 'failed-1')]));
        app()->instance(BatchRepository::class, $batches);
        app()->instance(JobRepository::class, $jobs);

        post('/horizon/batches/batch-1/retry')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Scheduled 1 failed batch job for retry.');

        Bus::assertDispatched(HorizonRetryFailedJob::class);

        Horizon::auth(static fn (): bool => false);
        post('/horizon/batches/batch-1/retry')->assertForbidden();
    });
});

function batchFeatureJob(int $index, string $id, string $batchId, string $status): object
{
    $job = horizonJob($index, $id);
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['data']['batchId'] = $batchId;
    $job->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    $job->status = $status;

    return $job;
}
