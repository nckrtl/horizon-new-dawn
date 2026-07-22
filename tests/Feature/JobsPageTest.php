<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));
});

describe('job pages', function (): void {
    it('renders the completed job list with scroll-safe rows', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getCompleted', new Collection([horizonJob(0)]));
        dashboardReturns($repository, 'countCompleted', 1);
        app()->instance(JobsData::class, new JobsData($repository));

        get('/horizon/jobs/completed')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Jobs/Index')
                ->where('meta.title', 'Completed Jobs')
                ->where('meta.activeNavigation', 'completed')
                ->where('type', 'completed')
                ->where('jobs.data.0.id', 'job-1')
                ->where('jobs.total', 1)
                ->missing('jobs.data.0.payload')
                ->missing('jobs.data.0.exception'));
    });

    it('renders a recent job detail and 404s a missing job', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getJobs', new Collection([horizonJob(0)]));
        app()->instance(JobsData::class, new JobsData($repository));

        get('/horizon/jobs/completed/job-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Jobs/Show')
                ->where('meta.title', 'Job Detail')
                ->where('job.id', 'job-1')
                ->where('job.payload.displayName', 'App\\Jobs\\ImportFeed')
                ->missing('job.exception'));

        $missingRepository = mockDashboardContract(JobRepository::class);
        dashboardReturns($missingRepository, 'getJobs', new Collection);
        app()->instance(JobsData::class, new JobsData($missingRepository));
        get('/horizon/jobs/completed/missing')->assertNotFound();
    });
});
