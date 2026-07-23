<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Console\WorkCommand as HorizonWorkCommand;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Http\Controllers\HomeController as HorizonHomeController;
use Laravel\Horizon\Http\Middleware\Authenticate;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Assets\AssetPath;
use NckRtl\HorizonNewDawn\Console\InstallCommand;
use NckRtl\HorizonNewDawn\Dashboard\DashboardPendingState;
use NckRtl\HorizonNewDawn\Http\Controllers\HomeController;
use NckRtl\HorizonNewDawn\Http\Middleware\HandleInertiaRequests;
use NckRtl\HorizonNewDawn\Jobs\ForgetsPendingJob;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Jobs\PendingJobPaginator;
use NckRtl\HorizonNewDawn\Queues\ClearQueueMetadata;
use NckRtl\HorizonNewDawn\Queues\ClearsQueueMetadata;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use NckRtl\HorizonNewDawn\Support\HorizonRuntime;
use NckRtl\HorizonNewDawn\Support\HorizonWorkCommandCompatibility;

final class HorizonNewDawnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/horizon-new-dawn.php',
            'horizon-new-dawn',
        );

        $this->app->bind(HorizonHomeController::class, HomeController::class);
        $this->app->bind(ClearsQueueMetadata::class, ClearQueueMetadata::class);
        $this->app->bind(ForgetsPendingJob::class, ClearQueueMetadata::class);
        $this->app->bind(
            JobsData::class,
            fn (): JobsData => new JobsData(
                $this->app->make(JobRepository::class),
                $this->app->make(RedisFactory::class),
                $this->app->make(PendingJobPaginator::class),
            ),
        );
        $this->app->resolving(
            HorizonWorkCommand::class,
            function (HorizonWorkCommand $command): void {
                $this->app
                    ->make(HorizonWorkCommandCompatibility::class)
                    ->prepare($command);
            },
        );
        $this->app->singleton(
            FrameworkCapabilities::class,
            fn (): FrameworkCapabilities => FrameworkCapabilities::detect(),
        );
        $this->app->bind(
            HorizonRuntime::class,
            fn (): HorizonRuntime => new HorizonRuntime(
                $this->app->make(MasterSupervisorRepository::class),
                $this->app->make(WorkloadRepository::class),
                $this->app->make(DashboardPendingState::class),
                $this->app->make(WaitTimeCalculator::class),
            ),
        );
        $this->app->booting(fn () => $this->registerRoutes());
    }

    public function boot(AssetPath $assetPath): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'horizon-new-dawn');

        $this->publishes([
            __DIR__.'/../config/horizon-new-dawn.php' => config_path('horizon-new-dawn.php'),
        ], 'horizon-new-dawn-config');

        $this->publishes([
            __DIR__.'/../dist/build' => $assetPath->absolute(),
        ], 'horizon-new-dawn-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $this->app->make(Router::class)->group([
            'domain' => config('horizon.domain'),
            'prefix' => config('horizon.path'),
            'middleware' => ['horizon', Authenticate::class, HandleInertiaRequests::class],
            'as' => 'horizon-new-dawn.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/horizon-new-dawn.php');
        });
    }
}
