<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests\Support;

use Closure;
use LogicException;
use Mockery;
use Mockery\CompositeExpectation;
use Mockery\Expectation;
use Mockery\MockInterface;
use Throwable;

/**
 * @template T of object
 *
 * @param  class-string<T>  $class
 * @return T&MockInterface
 */
function mockDashboardContract(string $class): object
{
    $mock = Mockery::mock($class);

    if (! $mock instanceof $class) {
        throw new LogicException("Mock does not implement {$class}.");
    }

    return $mock;
}

function dashboardReturns(MockInterface $mock, string $method, mixed $value): void
{
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation->andReturn($value);

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('andReturn', [$value]);

        return;
    }

    throw new LogicException("Unable to configure {$method} expectation.");
}

function dashboardReturnsUsing(MockInterface $mock, string $method, Closure $return): void
{
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation->andReturnUsing($return);

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('andReturnUsing', [$return]);

        return;
    }

    throw new LogicException("Unable to configure {$method} expectation.");
}

/** @param array<int, mixed> $arguments */
function dashboardReturnsFor(
    MockInterface $mock,
    string $method,
    array $arguments,
    mixed $value,
): void {
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation->with(...$arguments)->once()->andReturn($value);

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('with', $arguments);
        $expectation->__call('once', []);
        $expectation->andReturn($value);

        return;
    }

    throw new LogicException("Unable to configure {$method} expectation.");
}

function dashboardThrows(MockInterface $mock, string $method, Throwable $exception): void
{
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation->andThrow($exception);

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('andThrow', [$exception]);

        return;
    }

    throw new LogicException("Unable to configure {$method} exception.");
}

function dashboardNeverReceives(MockInterface $mock, string $method): void
{
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation->never();

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('never', []);

        return;
    }

    throw new LogicException("Unable to configure {$method} rejection.");
}

/** @param array<int, mixed> $arguments */
function dashboardThrowsFor(
    MockInterface $mock,
    string $method,
    array $arguments,
    Throwable $exception,
): void {
    $expectation = $mock->shouldReceive($method);

    if ($expectation instanceof Expectation) {
        $expectation->with(...$arguments)->once()->andThrow($exception);

        return;
    }

    if ($expectation instanceof CompositeExpectation) {
        $expectation->__call('with', $arguments);
        $expectation->__call('once', []);
        $expectation->__call('andThrow', [$exception]);

        return;
    }

    throw new LogicException("Unable to configure {$method} exception.");
}
