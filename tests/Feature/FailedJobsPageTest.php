<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
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

describe('failed job pages', function (): void {
    it('renders a safe failed list and detail', function (): void {
        $job = horizonJob(0, 'failed-1');
        $job->status = 'failed';
        $job->failed_at = '1784281003.5';
        $job->context = json_encode(['tenant' => 42], JSON_THROW_ON_ERROR);

        $repository = mockDashboardContract(JobRepository::class);
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($repository, 'getFailed', new Collection([$job]));
        dashboardReturns($repository, 'countFailed', 1);
        dashboardReturnsFor($repository, 'getJobs', [['failed-1']], new Collection([$job]));
        dashboardReturnsFor($tags, 'paginate', ['failed:tenant:42', 0, 50], [0 => 'failed-1']);
        dashboardReturnsFor($tags, 'count', ['failed:tenant:42'], 1);
        dashboardReturnsFor($repository, 'getJobs', [['failed-1'], 0], new Collection([$job]));
        app()->instance(FailedJobsData::class, new FailedJobsData(
            $repository,
            $tags,
            new JobsData($repository),
            new FailedJobRetryEligibility,
        ));

        get('/horizon/failed')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('FailedJobs/Index')
                ->where('meta.title', 'Failed Jobs')
                ->where('meta.activeNavigation', 'failed')
                ->where('jobs.data.0.id', 'failed-1')
                ->where('jobs.data.0.retried', false)
                ->where('jobs.data.0.retryEligible', true)
                ->missing('jobs.data.0.payload')
                ->missing('jobs.data.0.exception')
                ->missing('jobs.data.0.context'));

        get('/horizon/failed?tag=tenant%3A42')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('FailedJobs/Index')
                ->where('query', 'tenant:42')
                ->where('jobs.total', 1)
                ->where('jobs.data.0.id', 'failed-1'));

        get('/horizon/failed/failed-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('FailedJobs/Show')
                ->where('job.id', 'failed-1')
                ->where('job.context.tenant', 42)
                ->where('job.payload.displayName', 'App\\Jobs\\ImportFeed')
                ->where('job.retryEligible', true)
                ->where('job.exception', 'sensitive trace'));
    });

    it('retries one failed job and redirects with feedback', function (): void {
        Bus::fake();

        $job = horizonJob(0, 'failed-1');
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'findFailed', ['failed-1'], $job);
        app()->instance(JobRepository::class, $repository);

        post('/horizon/failed/failed-1/retry')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Retry scheduled for failed-1.');

        Bus::assertDispatched(
            HorizonRetryFailedJob::class,
            fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
        );
    });

    it('reports when one failed job is no longer eligible for retry', function (): void {
        Bus::fake();

        $job = horizonJob(0, 'failed-1');
        $job->retried_by = json_encode([
            ['id' => 'retry-1', 'status' => 'completed'],
        ], JSON_THROW_ON_ERROR);
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'findFailed', ['failed-1'], $job);
        app()->instance(JobRepository::class, $repository);

        post('/horizon/failed/failed-1/retry')
            ->assertRedirect()
            ->assertSessionHas(
                'toast.error',
                'No retry was scheduled because failed-1 is no longer eligible.',
            );

        Bus::assertNothingDispatched();
    });

    it('retries every eligible failed job and reports the scheduled count', function (): void {
        Bus::fake();

        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getFailed', new Collection([
            horizonJob(0, 'failed-1'),
            horizonJob(1, 'failed-2'),
        ]));
        app()->instance(JobRepository::class, $repository);

        post('/horizon/failed/retry-all')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Scheduled 2 failed jobs for retry.');

        Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 2);
    });

    it('removes a failed job from Horizon and Laravel storage', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($repository, 'deleteFailed', ['failed-1'], 1);
        app()->instance(JobRepository::class, $repository);

        $failedJobs = mockDashboardContract(FailedJobProviderInterface::class);
        dashboardReturnsFor($failedJobs, 'forget', ['failed-1'], true);
        app()->instance(FailedJobProviderInterface::class, $failedJobs);

        delete('/horizon/failed/failed-1')
            ->assertRedirect('/horizon/failed')
            ->assertSessionHas('toast.success', 'Removed failed job failed-1.');
    });

    it('clears every failed job from Horizon and Laravel storage', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getFailed', new Collection([
            horizonJob(0, 'failed-1'),
            horizonJob(1, 'failed-2'),
        ]));
        dashboardReturnsFor($repository, 'deleteFailed', ['failed-1'], 1);
        dashboardReturnsFor($repository, 'deleteFailed', ['failed-2'], 1);
        app()->instance(JobRepository::class, $repository);

        $failedJobs = mockDashboardContract(FailedJobProviderInterface::class);
        dashboardReturnsFor($failedJobs, 'forget', ['failed-1'], true);
        dashboardReturnsFor($failedJobs, 'forget', ['failed-2'], true);
        app()->instance(FailedJobProviderInterface::class, $failedJobs);

        delete('/horizon/failed')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Cleared 2 failed jobs.');
    });

    it('honors Horizon authorization for failed-job mutations', function (): void {
        Horizon::auth(static fn (): bool => false);

        post('/horizon/failed/failed-1/retry')->assertForbidden();
        post('/horizon/failed/retry-all')->assertForbidden();
        delete('/horizon/failed')->assertForbidden();
        delete('/horizon/failed/failed-1')->assertForbidden();

        Bus::fake();
        Bus::assertNothingDispatched();
    });
});
