<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use NckRtl\HorizonNewDawn\Supervisors\SupervisorDetails;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrowsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('SupervisorDetails', function (): void {
    it('normalizes the effective runtime supervisor policy', function (): void {
        config()->set('queue.connections.redis.retry_after', 120);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturnsFor($supervisors, 'find', ['horizon-web-01:supervisor-1'], (object) [
            'name' => 'horizon-web-01:supervisor-1',
            'master' => 'horizon-web-01',
            'pid' => '8124',
            'status' => 'running',
            'processes' => [
                'redis:critical' => 3,
                'redis:default' => 2,
                'redis:mail' => 1,
            ],
            'options' => [
                'connection' => 'redis',
                'queue' => 'critical,default,mail',
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 2,
                'maxProcesses' => 12,
                'balanceCooldown' => 3,
                'balanceMaxShift' => 2,
                'memory' => 256,
                'timeout' => 90,
                'maxTries' => 3,
                'backoff' => 10,
                'maxJobs' => 500,
                'maxTime' => 3600,
                'sleep' => 3,
                'rest' => 0,
                'force' => true,
                'nice' => 5,
            ],
        ]);

        $result = (new SupervisorDetails(
            $supervisors,
            app(ConfigRepository::class),
        ))->find('horizon-web-01:supervisor-1');

        expect($result->toArray())->toBe([
            'available' => true,
            'supervisor' => [
                'id' => 'horizon-web-01:supervisor-1',
                'name' => 'supervisor-1',
                'master' => 'horizon-web-01',
                'pid' => 8124,
                'status' => 'running',
                'connection' => 'redis',
                'queues' => ['critical', 'default', 'mail'],
                'processes' => 6,
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 2,
                'maxProcesses' => 12,
                'balanceCooldown' => 3,
                'balanceMaxShift' => 2,
                'memory' => 256,
                'timeout' => 90,
                'retryAfter' => 120,
                'maxTries' => 3,
                'backoff' => 10,
                'maxJobs' => 500,
                'maxTime' => 3600,
                'sleep' => 3,
                'rest' => 0,
                'force' => true,
                'nice' => 5,
                'warnings' => [],
            ],
            'message' => null,
        ]);
    });

    it('warns when timeout is not shorter than retry after', function (int $timeout): void {
        config()->set('queue.connections.redis.retry_after', 90);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturnsFor($supervisors, 'find', ['machine:supervisor-1'], (object) [
            'name' => 'machine:supervisor-1',
            'master' => 'machine',
            'status' => 'running',
            'processes' => ['redis:default' => 1],
            'options' => [
                'connection' => 'redis',
                'queue' => 'default',
                'timeout' => $timeout,
            ],
        ]);

        $result = (new SupervisorDetails(
            $supervisors,
            app(ConfigRepository::class),
        ))->find('machine:supervisor-1');

        expect($result->supervisor?->warnings[0]->toArray())->toBe([
            'title' => 'Unsafe timeout configuration',
            'description' => "The {$timeout}-second worker timeout must remain shorter than the 90-second retry-after value to prevent overlapping attempts.",
        ]);
    })->with([90, 120]);

    it('preserves unavailable options and falls back to process queue order', function (): void {
        config()->set('queue.connections.redis.retry_after', null);

        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturnsFor($supervisors, 'find', ['machine:supervisor-1'], (object) [
            'name' => 'machine:supervisor-1',
            'status' => 'paused',
            'processes' => [
                'redis:mail' => 1,
                'redis:default' => 2,
            ],
            'options' => ['connection' => 'redis'],
        ]);

        $result = (new SupervisorDetails(
            $supervisors,
            app(ConfigRepository::class),
        ))->find('machine:supervisor-1');

        expect($result->supervisor)->not->toBeNull();

        if ($result->supervisor === null) {
            throw new RuntimeException('Expected an active supervisor.');
        }

        expect($result->supervisor->master)->toBe('machine')
            ->and($result->supervisor->queues)->toBe(['mail', 'default'])
            ->and($result->supervisor->processes)->toBe(3)
            ->and($result->supervisor->retryAfter)->toBeNull()
            ->and($result->supervisor->balance)->toBeNull()
            ->and($result->supervisor->timeout)->toBeNull()
            ->and($result->supervisor->force)->toBeNull();
    });

    it('returns an available empty result when the supervisor is no longer active', function (): void {
        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardReturnsFor($supervisors, 'find', ['missing'], null);

        $result = (new SupervisorDetails(
            $supervisors,
            app(ConfigRepository::class),
        ))->find('missing');

        expect($result->toArray())->toBe([
            'available' => true,
            'supervisor' => null,
            'message' => null,
        ]);
    });

    it('returns a safe unavailable result when Horizon cannot be read', function (): void {
        $supervisors = mockDashboardContract(SupervisorRepository::class);
        dashboardThrowsFor(
            $supervisors,
            'find',
            ['machine:supervisor-1'],
            new RuntimeException('redis password leaked'),
        );

        $result = (new SupervisorDetails(
            $supervisors,
            app(ConfigRepository::class),
        ))->find('machine:supervisor-1');

        expect($result->toArray())->toBe([
            'available' => false,
            'supervisor' => null,
            'message' => 'Horizon supervisor details are currently unavailable.',
        ]);
    });
});
