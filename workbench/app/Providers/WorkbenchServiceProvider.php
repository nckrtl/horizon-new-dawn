<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Horizon::auth(
            static fn (Request $request): bool => app()->environment('local'),
        );
    }
}
