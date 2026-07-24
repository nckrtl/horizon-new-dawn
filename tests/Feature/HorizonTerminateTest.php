<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use Laravel\Horizon\SupervisorCommands\Pause;
use Laravel\Horizon\SupervisorCommands\Terminate;
use Mockery\MockInterface;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardNeverReceives;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
    MasterSupervisor::determineNameUsing(static fn (): string => 'local-host');
    Cache::forget('horizon:terminate:wait');
    Cache::forget('illuminate:queue:restart');
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
    MasterSupervisor::$nameResolver = null;
    Carbon::setTestNow();
});

it('queues terminate for every local Horizon instance and preserves restart state', function (): void {
    config()->set('horizon.fast_termination', true);
    Carbon::setTestNow('2026-07-22 00:00:00');

    $masters = bindProcessMasterRepository();
    dashboardReturnsFor($masters, 'all', [], [
        (object) ['name' => 'local-host-a1b2'],
        (object) ['name' => 'remote-host-c3d4'],
        (object) ['name' => 'local-host-2-g7h8'],
        (object) ['name' => 'local-host-e5f6'],
    ]);
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['master:local-host-a1b2', Terminate::class, []],
        null,
    );
    dashboardReturnsFor(
        $commands,
        'push',
        ['master:local-host-e5f6', Terminate::class, []],
        null,
    );

    post('/horizon/instances/terminate')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Horizon termination requested.');

    expect(Cache::get('horizon:terminate:wait'))->toBeFalse()
        ->and(Cache::get('illuminate:queue:restart'))->toBe(now()->getTimestamp());
});

it('reports a failed Horizon terminate command queue write', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor($masters, 'all', [], [(object) ['name' => 'local-host-a1b2']]);
    $commands = bindProcessCommandQueue();
    dashboardThrowsFor(
        $commands,
        'push',
        ['master:local-host-a1b2', Terminate::class, []],
        new RuntimeException('Redis unavailable.'),
    );

    postJson('/horizon/instances/terminate')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Horizon could not be terminated.');
});

it('honors Horizon authorization when terminating Horizon', function (): void {
    Horizon::auth(static fn (): bool => false);

    post('/horizon/instances/terminate')->assertForbidden();
});

it('queues pause for the exact local Horizon instance', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor(
        $masters,
        'find',
        ['local-host-a1b2'],
        (object) ['name' => 'local-host-a1b2'],
    );
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['master:local-host-a1b2', Pause::class, []],
        null,
    );

    postJson('/horizon/instances/local-host-a1b2/pause')
        ->assertAccepted()
        ->assertJsonPath('message', 'Horizon pause requested.');
});

it('queues continue for the exact local Horizon instance', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor(
        $masters,
        'find',
        ['local-host-a1b2'],
        (object) ['name' => 'local-host-a1b2'],
    );
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['master:local-host-a1b2', ContinueWorking::class, []],
        null,
    );

    deleteJson('/horizon/instances/local-host-a1b2/pause')
        ->assertAccepted()
        ->assertJsonPath('message', 'Horizon continue requested.');
});

it('rejects a missing Horizon instance', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor($masters, 'find', ['local-host-missing'], null);
    bindProcessCommandQueue();

    postJson('/horizon/instances/local-host-missing/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Horizon could not be paused.');
});

it('rejects an instance from a similarly named host sharing Redis', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor(
        $masters,
        'find',
        ['local-host-2-a1b2'],
        (object) ['name' => 'local-host-2-a1b2'],
    );
    $commands = bindProcessCommandQueue();
    dashboardNeverReceives($commands, 'push');

    postJson('/horizon/instances/local-host-2-a1b2/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Horizon could not be paused.');
});

it('rejects a stale Horizon instance record', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor(
        $masters,
        'find',
        ['local-host-a1b2'],
        (object) ['name' => 'local-host-e5f6'],
    );
    bindProcessCommandQueue();

    postJson('/horizon/instances/local-host-a1b2/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Horizon could not be paused.');
});

it('reports a failed Horizon instance command queue write', function (): void {
    $masters = bindProcessMasterRepository();
    dashboardReturnsFor(
        $masters,
        'find',
        ['local-host-a1b2'],
        (object) ['name' => 'local-host-a1b2'],
    );
    $commands = bindProcessCommandQueue();
    dashboardThrowsFor(
        $commands,
        'push',
        ['master:local-host-a1b2', Pause::class, []],
        new RuntimeException('Redis unavailable.'),
    );

    postJson('/horizon/instances/local-host-a1b2/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Horizon could not be paused.');
});

it('queues pause for the exact active supervisor', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor(
        $supervisors,
        'find',
        ['local-host-a1b2:supervisor-1'],
        (object) ['name' => 'local-host-a1b2:supervisor-1'],
    );
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['local-host-a1b2:supervisor-1', Pause::class, []],
        null,
    );

    postJson('/horizon/supervisors/local-host-a1b2%3Asupervisor-1/pause')
        ->assertAccepted()
        ->assertJsonPath('message', 'Supervisor pause requested.');
});

it('queues continue for the exact active supervisor', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor(
        $supervisors,
        'find',
        ['local-host-a1b2:supervisor-1'],
        (object) ['name' => 'local-host-a1b2:supervisor-1'],
    );
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['local-host-a1b2:supervisor-1', ContinueWorking::class, []],
        null,
    );

    deleteJson('/horizon/supervisors/local-host-a1b2%3Asupervisor-1/pause')
        ->assertAccepted()
        ->assertJsonPath('message', 'Supervisor continue requested.');
});

it('queues pause for a slash-bearing supervisor name without consuming the pause suffix', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor(
        $supervisors,
        'find',
        ['local-host-a1b2:imports/worker'],
        (object) ['name' => 'local-host-a1b2:imports/worker'],
    );
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['local-host-a1b2:imports/worker', Pause::class, []],
        null,
    );

    postJson('/horizon/supervisors/local-host-a1b2%3Aimports%2Fworker/pause')
        ->assertAccepted()
        ->assertJsonPath('message', 'Supervisor pause requested.');
});

it('queues continue for a slash-bearing supervisor name without consuming the pause suffix', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor(
        $supervisors,
        'find',
        ['local-host-a1b2:imports/worker'],
        (object) ['name' => 'local-host-a1b2:imports/worker'],
    );
    $commands = bindProcessCommandQueue();
    dashboardReturnsFor(
        $commands,
        'push',
        ['local-host-a1b2:imports/worker', ContinueWorking::class, []],
        null,
    );

    deleteJson('/horizon/supervisors/local-host-a1b2%3Aimports%2Fworker/pause')
        ->assertAccepted()
        ->assertJsonPath('message', 'Supervisor continue requested.');
});

it('rejects a missing supervisor', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor($supervisors, 'find', ['missing-supervisor'], null);
    bindProcessCommandQueue();

    postJson('/horizon/supervisors/missing-supervisor/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Supervisor could not be paused.');
});

it('rejects a supervisor from a similarly named host sharing Redis', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor(
        $supervisors,
        'find',
        ['local-host-2-a1b2:supervisor-1'],
        (object) [
            'name' => 'local-host-2-a1b2:supervisor-1',
            'master' => 'local-host-2-a1b2',
        ],
    );
    $commands = bindProcessCommandQueue();
    dashboardNeverReceives($commands, 'push');

    postJson('/horizon/supervisors/local-host-2-a1b2%3Asupervisor-1/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Supervisor could not be paused.');
});

it('reports a failed supervisor command queue write', function (): void {
    $supervisors = bindProcessSupervisorRepository();
    dashboardReturnsFor(
        $supervisors,
        'find',
        ['local-host-a1b2:supervisor-1'],
        (object) ['name' => 'local-host-a1b2:supervisor-1'],
    );
    $commands = bindProcessCommandQueue();
    dashboardThrowsFor(
        $commands,
        'push',
        ['local-host-a1b2:supervisor-1', Pause::class, []],
        new RuntimeException('Redis unavailable.'),
    );

    postJson('/horizon/supervisors/local-host-a1b2%3Asupervisor-1/pause')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Supervisor could not be paused.');
});

function bindProcessMasterRepository(): MasterSupervisorRepository&MockInterface
{
    $repository = mockDashboardContract(MasterSupervisorRepository::class);
    app()->instance(MasterSupervisorRepository::class, $repository);

    return $repository;
}

function bindProcessSupervisorRepository(): SupervisorRepository&MockInterface
{
    $repository = mockDashboardContract(SupervisorRepository::class);
    app()->instance(SupervisorRepository::class, $repository);

    return $repository;
}

function bindProcessCommandQueue(): HorizonCommandQueue&MockInterface
{
    $commands = mockDashboardContract(HorizonCommandQueue::class);
    app()->instance(HorizonCommandQueue::class, $commands);

    return $commands;
}
