<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Symfony\Component\Process\Process;
use Workbench\App\Jobs\FailingJob;
use Workbench\App\Jobs\SucceedingJob;

it('processes successful and failing jobs through a real Horizon worker', function (): void {
    $environment = compatibilityEnvironment();
    configureCompatibilityRedis($environment);

    $redis = app(RedisFactory::class)->connection('default');
    $redis->flushdb();

    expect(app(JobRepository::class)->countPending())->toBe(0);

    $horizon = new Process(
        [PHP_BINARY, 'vendor/bin/testbench', 'horizon', '--no-interaction'],
        compatibilityPackagePath(),
        $environment,
    );
    $horizon->setTimeout(null);
    $horizonStarted = false;

    try {
        dispatch((new SucceedingJob)->onConnection('redis')->onQueue('default'));
        dispatch((new FailingJob)->onConnection('redis')->onQueue('default'));

        expect(app(JobRepository::class)->countPending())->toBe(2);

        $horizon->start();
        $horizonStarted = true;

        waitForHorizon(
            fn (): bool => app(JobRepository::class)->countCompleted() === 1
                && app(JobRepository::class)->countFailed() === 1
                && app(JobRepository::class)->countPending() === 0,
            $horizon,
        );

        expect(app(JobRepository::class)->countCompleted())->toBe(1)
            ->and(app(JobRepository::class)->countFailed())->toBe(1)
            ->and(app(JobRepository::class)->countPending())->toBe(0);
    } finally {
        if ($horizonStarted) {
            $horizon->stop(10, SIGTERM);
        }

        $redis->flushdb();
    }
});

/** @return array<string, string> */
function compatibilityEnvironment(): array
{
    $host = getenv('HORIZON_COMPATIBILITY_REDIS_HOST');
    $port = getenv('HORIZON_COMPATIBILITY_REDIS_PORT');
    $database = getenv('HORIZON_COMPATIBILITY_REDIS_DB');

    if (! is_string($host) || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('Set HORIZON_COMPATIBILITY_REDIS_HOST to localhost or 127.0.0.1.');
    }

    if (! is_string($port) || filter_var($port, FILTER_VALIDATE_INT) === false) {
        throw new RuntimeException('Set HORIZON_COMPATIBILITY_REDIS_PORT to an isolated local Redis port.');
    }

    if (! is_string($database) || filter_var($database, FILTER_VALIDATE_INT) === false) {
        throw new RuntimeException('Set HORIZON_COMPATIBILITY_REDIS_DB to an isolated Redis database.');
    }

    $prefix = 'horizon_new_dawn_compatibility_'.bin2hex(random_bytes(6)).'_';

    return [
        'APP_ENV' => 'local',
        'HORIZON_PREFIX' => $prefix.'horizon:',
        'QUEUE_CONNECTION' => 'redis',
        'REDIS_CLIENT' => 'predis',
        'REDIS_DB' => $database,
        'REDIS_HOST' => $host,
        'REDIS_PORT' => $port,
        'REDIS_PREFIX' => $prefix.'database:',
    ];
}

function compatibilityPackagePath(): string
{
    return dirname(__DIR__, 2);
}

/** @param array<string, string> $environment */
function configureCompatibilityRedis(array $environment): void
{
    config([
        'database.redis.client' => $environment['REDIS_CLIENT'],
        'database.redis.options.prefix' => $environment['REDIS_PREFIX'],
        'database.redis.default.host' => $environment['REDIS_HOST'],
        'database.redis.default.port' => $environment['REDIS_PORT'],
        'database.redis.default.database' => $environment['REDIS_DB'],
        'horizon.prefix' => $environment['HORIZON_PREFIX'],
        'horizon.use' => 'default',
        'queue.default' => 'redis',
        'queue.connections.redis.connection' => 'default',
        'queue.connections.redis.queue' => 'default',
    ]);

    Horizon::use('default');
    app()->forgetInstance('redis');
}

/** @param Closure(): bool $condition */
function waitForHorizon(Closure $condition, Process $horizon): void
{
    $deadline = microtime(true) + 20;

    while (microtime(true) < $deadline) {
        if ($condition()) {
            return;
        }

        if ($horizon->isTerminated()) {
            throw new RuntimeException(
                "Horizon terminated before processing the smoke-test jobs.\n"
                .$horizon->getOutput().$horizon->getErrorOutput(),
            );
        }

        usleep(100_000);
    }

    $jobs = app(JobRepository::class);

    throw new RuntimeException(
        "Horizon did not process the smoke-test jobs within 20 seconds.\n"
        .sprintf(
            "Observed counts: completed=%d failed=%d pending=%d.\n",
            $jobs->countCompleted(),
            $jobs->countFailed(),
            $jobs->countPending(),
        )
        .$horizon->getOutput().$horizon->getErrorOutput(),
    );
}
