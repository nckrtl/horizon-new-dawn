<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Jobs\MonitorTag as HorizonMonitorTag;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use Laravel\Horizon\Jobs\StopMonitoringTag as HorizonStopMonitoringTag;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringData;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsUsing;
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

describe('monitoring pages', function (): void {
    it('renders monitored tags and route-backed recent and failed tabs', function (): void {
        config()->set('horizon.silenced_tags', ['checkout']);
        config()->set('horizon.trim.monitored', 120);
        config()->set('horizon.trim.failed', 240);

        $recent = horizonJob(0, 'job-1');
        $failed = horizonJob(0, 'failed-1');
        $failed->completed_at = null;
        $failed->failed_at = '1784281003.00';

        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['checkout']);
        dashboardReturnsUsing($tags, 'count', static fn (string $tag): int => match ($tag) {
            'checkout' => 2,
            'failed:checkout' => 1,
            default => 0,
        });
        dashboardReturnsUsing($tags, 'paginate', static function (string $tag, int $startingAt = 0, int $limit = 25): array {
            return match (true) {
                $tag === 'checkout' && $limit === 1 => [0 => 'job-1'],
                $tag === 'failed:checkout' && $limit === 1 => [0 => 'failed-1'],
                $tag === 'checkout' => [0 => 'job-1'],
                $tag === 'failed:checkout' => [0 => 'failed-1'],
                default => [],
            };
        });
        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsUsing($jobs, 'getJobs', function (array $ids) use ($recent, $failed): Collection {
            $map = [
                'job-1' => $recent,
                'failed-1' => $failed,
            ];

            return new Collection(array_values(array_filter(
                array_map(static fn (string $id): ?object => $map[$id] ?? null, $ids),
            )));
        });
        app()->instance(MonitoringData::class, new MonitoringData($tags, $jobs, new JobsData($jobs)));

        get('/horizon/monitoring')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Monitoring/Index')
                ->where('meta.activeNavigation', 'monitoring')
                ->where('tags.data.0.tag', 'checkout')
                ->where('tags.data.0.trackedCount', 2)
                ->where('tags.data.0.failedCount', 1)
                ->where('tags.data.0.silenced', true)
                ->where('monitoredTags', ['checkout']));

        get('/horizon/monitoring/checkout/jobs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Monitoring/Show')
                ->where('tag', 'checkout')
                ->where('status', 'jobs')
                ->where('summary.trackedCount', 2)
                ->where('summary.failedCount', 1)
                ->where('summary.silenced', true)
                ->where('summary.monitoredRetentionMinutes', 120)
                ->where('summary.failedRetentionMinutes', 240)
                ->where('jobs.data.0.id', 'job-1'));

        get('/horizon/monitoring/checkout/failed')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Monitoring/Show')
                ->where('status', 'failed')
                ->where('jobs.data.0.id', 'failed-1'));
    });

    it('renders a monitored tag containing an encoded slash', function (): void {
        $recent = horizonJob(0, 'job-1');
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['customer/42']);
        dashboardReturnsUsing($tags, 'count', static fn (string $tag): int => $tag === 'customer/42' ? 1 : 0);
        dashboardReturnsUsing($tags, 'paginate', static fn (string $tag): array => $tag === 'customer/42' ? ['job-1'] : []);

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturns($jobs, 'getJobs', new Collection([$recent]));

        app()->instance(MonitoringData::class, new MonitoringData($tags, $jobs, new JobsData($jobs)));

        get('/horizon/monitoring/customer%2F42/jobs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Monitoring/Show')
                ->where('tag', 'customer/42')
                ->where('status', 'jobs')
                ->where('jobs.data.0.id', 'job-1'));
    });

    it('validates and trims a new monitored tag', function (): void {
        Bus::fake();

        post('/horizon/monitoring', ['tag' => '   '])
            ->assertRedirect()
            ->assertSessionHasErrors('tag');

        post('/horizon/monitoring', ['tag' => '  checkout  '])
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Now monitoring checkout.');

        Bus::assertDispatched(
            HorizonMonitorTag::class,
            fn (HorizonMonitorTag $job): bool => $job->tag === 'checkout',
        );
    });

    it('rejects tags that collide with Horizon storage', function (string $tag): void {
        Bus::fake();

        post('/horizon/monitoring', ['tag' => $tag])
            ->assertRedirect()
            ->assertSessionHasErrors('tag');

        Bus::assertNotDispatched(HorizonMonitorTag::class);
    })->with([
        'pending jobs index' => 'pending_jobs',
        'failed jobs index' => 'failed_jobs',
        'global job id counter' => 'job_id',
        'master index' => 'masters',
        'command queue namespace' => 'commands:horizon-host',
        'failed tag namespace' => 'failed:checkout',
        'job metrics namespace' => 'snapshot:job:App\\Jobs\\ImportUsers',
        'notification lock namespace' => 'notification:long-wait',
        'orphan process namespace' => 'horizon-host:orphans',
    ]);

    it('allows ordinary application tag formats', function (string $tag): void {
        Bus::fake();

        post('/horizon/monitoring', ['tag' => $tag])
            ->assertRedirect()
            ->assertSessionHas('toast.success', "Now monitoring {$tag}.");

        Bus::assertDispatched(
            HorizonMonitorTag::class,
            fn (HorizonMonitorTag $job): bool => $job->tag === $tag,
        );
    })->with([
        'model tag' => 'App\\Models\\User:42',
        'tenant tag' => 'tenant:acme',
        'namespace tag' => 'namespace\\job',
        'slash tag' => 'customer/42',
    ]);

    it('stops monitoring a tag and honors Horizon authorization', function (): void {
        Bus::fake();
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['checkout']);
        app()->instance(TagRepository::class, $tags);

        delete('/horizon/monitoring/actions/stop/checkout')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Stopped monitoring checkout.');

        Bus::assertDispatched(
            HorizonStopMonitoringTag::class,
            fn (HorizonStopMonitoringTag $job): bool => $job->tag === 'checkout',
        );

        Horizon::auth(static fn (): bool => false);
        post('/horizon/monitoring', ['tag' => 'denied'])->assertForbidden();
    });

    it('stops monitoring a slash-bearing tag beginning with an action-like segment', function (): void {
        Bus::fake();
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['jobs/customer']);
        app()->instance(TagRepository::class, $tags);

        delete('/horizon/monitoring/actions/stop/jobs%2Fcustomer')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Stopped monitoring jobs/customer.');

        Bus::assertDispatched(
            HorizonStopMonitoringTag::class,
            fn (HorizonStopMonitoringTag $job): bool => $job->tag === 'jobs/customer',
        );
    });

    it('clears recent jobs while keeping the tag monitored', function (): void {
        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['customer/jobs']);
        dashboardReturnsUsing($tags, 'paginate', static fn (string $tag, int $startingAt, int $limit): array => match ([$tag, $startingAt, $limit]) {
            ['customer/jobs', 0, 50] => ['recent-1', 'recent-2'],
            default => [],
        });
        dashboardReturnsFor($tags, 'forget', ['customer/jobs'], null);

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturnsFor($jobs, 'deleteMonitored', [['recent-1', 'recent-2']], null);

        app()->instance(TagRepository::class, $tags);
        app()->instance(JobRepository::class, $jobs);

        delete('/horizon/monitoring/actions/clear-jobs/customer%2Fjobs')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Cleared 2 recent jobs from customer/jobs.');

        $tags->shouldNotHaveReceived('stopMonitoring');
    });

    it('retries failed jobs for a monitored tag', function (): void {
        Bus::fake();

        $failed = horizonJob(0, 'failed-1');
        $retried = horizonJob(1, 'failed-2');
        $payload = json_decode((string) $retried->payload, true, flags: JSON_THROW_ON_ERROR);
        $retried->payload = json_encode([...$payload, 'retry_of' => 'original-2'], JSON_THROW_ON_ERROR);

        $tags = mockDashboardContract(TagRepository::class);
        dashboardReturns($tags, 'monitoring', ['customer/42']);
        dashboardReturnsUsing($tags, 'paginate', static fn (string $tag, int $startingAt, int $limit): array => match ([$tag, $startingAt, $limit]) {
            ['failed:customer/42', 0, 50] => ['failed-1', 'failed-2'],
            default => [],
        });

        $jobs = mockDashboardContract(JobRepository::class);
        dashboardReturns($jobs, 'getJobs', new Collection([$failed, $retried]));

        app()->instance(TagRepository::class, $tags);
        app()->instance(JobRepository::class, $jobs);

        post('/horizon/monitoring/actions/retry-failed/customer%2F42')
            ->assertRedirect()
            ->assertSessionHas(
                'toast.success',
                'Scheduled 2 failed jobs tagged customer/42 for retry.',
            );

        Bus::assertDispatched(
            HorizonRetryFailedJob::class,
            fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-1',
        );
        Bus::assertDispatched(
            HorizonRetryFailedJob::class,
            fn (HorizonRetryFailedJob $job): bool => $job->id === 'failed-2',
        );
        Bus::assertDispatchedTimes(HorizonRetryFailedJob::class, 2);
    });
});
