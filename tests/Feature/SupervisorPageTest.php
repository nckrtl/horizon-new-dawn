<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Horizon;
use NckRtl\HorizonNewDawn\Supervisors\SupervisorDetails;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Horizon::auth(static fn (): bool => true);

    $masters = mockDashboardContract(MasterSupervisorRepository::class);
    dashboardReturns($masters, 'all', [(object) ['status' => 'running']]);
    app()->instance(HorizonRuntime::class, new HorizonRuntime($masters));
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

describe('supervisor detail page', function (): void {
    it('renders an active supervisor through its dedicated route', function (): void {
        config()->set('queue.connections.redis.retry_after', 120);

        bindSupervisorDetails((object) [
            'name' => 'horizon-web-01:supervisor-1',
            'master' => 'horizon-web-01',
            'status' => 'running',
            'processes' => ['redis:critical,default' => 4],
            'options' => [
                'connection' => 'redis',
                'queue' => 'critical,default',
                'balance' => 'auto',
                'timeout' => 90,
            ],
        ]);

        get('/horizon/supervisors/horizon-web-01%3Asupervisor-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Supervisors/Show')
                ->where('meta.title', 'supervisor-1')
                ->where('meta.activeNavigation', 'instances')
                ->where('supervisorDetails.available', true)
                ->where('supervisorDetails.supervisor.id', 'horizon-web-01:supervisor-1')
                ->where('supervisorDetails.supervisor.queues', ['critical', 'default'])
                ->where('supervisorDetails.supervisor.retryAfter', 120)
                ->where('supervisorDetails.message', null));
    });

    it('returns 404 when the active supervisor has disappeared', function (): void {
        bindSupervisorDetails(null, 'missing');

        get('/horizon/supervisors/missing')->assertNotFound();
    });

    it('renders a safe unavailable state when Horizon cannot be read', function (): void {
        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardThrowsFor(
            $supervisors,
            'find',
            ['machine:supervisor-1'],
            new RuntimeException('redis password leaked'),
        );
        app()->instance(SupervisorDetails::class, new SupervisorDetails(
            $supervisors,
            app(ConfigRepository::class),
        ));

        get('/horizon/supervisors/machine%3Asupervisor-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Supervisors/Show')
                ->where('meta.title', 'Supervisor')
                ->where('supervisorDetails.available', false)
                ->where('supervisorDetails.supervisor', null)
                ->where('supervisorDetails.message', 'Horizon supervisor details are currently unavailable.')
                ->missing('redis password leaked'));
    });

    it('honors Horizon authorization', function (): void {
        Horizon::auth(static fn (): bool => false);

        get('/horizon/supervisors/machine%3Asupervisor-1')->assertForbidden();
    });
});

function bindSupervisorDetails(?object $supervisor, string $name = 'horizon-web-01:supervisor-1'): void
{
    $supervisors = mockDashboardContract(SupervisorRepository::class);
    dashboardReturnsFor($supervisors, 'find', [$name], $supervisor);
    app()->instance(SupervisorDetails::class, new SupervisorDetails(
        $supervisors,
        app(ConfigRepository::class),
    ));
}
