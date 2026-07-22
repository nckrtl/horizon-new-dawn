<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests;

use Illuminate\Filesystem\Filesystem;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Horizon\HorizonServiceProvider;
use NckRtl\HorizonNewDawn\HorizonNewDawnServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Filesystem::class)->copyDirectory(
            __DIR__.'/../dist/build',
            public_path('vendor/horizon-new-dawn/build'),
        );
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        $providers = [
            InertiaServiceProvider::class,
            LaravelDataServiceProvider::class,
            HorizonServiceProvider::class,
        ];

        if (class_exists(HorizonNewDawnServiceProvider::class)) {
            $providers[] = HorizonNewDawnServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app->detectEnvironment(static fn (): string => 'local');
        $app['config']->set('app.env', 'local');
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }
}
