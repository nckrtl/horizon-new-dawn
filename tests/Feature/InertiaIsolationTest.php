<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use NckRtl\HorizonNewDawn\Http\Middleware\HandleInertiaRequests;

use function Pest\Laravel\get;

describe('Inertia isolation', function (): void {
    it('applies package Inertia middleware only to New Dawn page routes', function (): void {
        $pageRoute = Route::getRoutes()->getByName('horizon-new-dawn.dashboard');
        $fallbackRoute = Route::getRoutes()->getByName('horizon.index');
        $apiRoute = Route::getRoutes()->getByName('horizon.stats.index');

        expect($pageRoute)->not->toBeNull()
            ->and($fallbackRoute)->not->toBeNull()
            ->and($apiRoute)->not->toBeNull()
            ->and($pageRoute?->gatherMiddleware())->toContain(HandleInertiaRequests::class)
            ->and($fallbackRoute?->gatherMiddleware())->not->toContain(HandleInertiaRequests::class)
            ->and($apiRoute?->gatherMiddleware())->not->toContain(HandleInertiaRequests::class);
    });

    it('does not share New Dawn runtime data with host Inertia routes', function (): void {
        Route::get('/host-inertia', fn () => Inertia::render('Host'));

        get('/host-inertia', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'Host')
            ->assertJsonMissingPath('props.horizon')
            ->assertJsonMissingPath('props.navigationCounts')
            ->assertJsonMissingPath('props.meta');
    });

    it('versions New Dawn requests from the published package asset manifest', function (): void {
        $assetsPath = 'vendor/horizon-new-dawn-version-test/build';
        $manifestDirectory = public_path($assetsPath.'/.vite');
        $manifestPath = $manifestDirectory.'/manifest.json';
        $filesystem = app(Filesystem::class);

        config()->set('horizon-new-dawn.assets_path', $assetsPath);
        $filesystem->ensureDirectoryExists($manifestDirectory);

        try {
            $firstManifest = json_encode([
                'resources/js/app.tsx' => [
                    'file' => 'assets/app-first.js',
                    'css' => [],
                    'isEntry' => true,
                ],
            ], JSON_THROW_ON_ERROR);
            $secondManifest = json_encode([
                'resources/js/app.tsx' => [
                    'file' => 'assets/app-second.js',
                    'css' => [],
                    'isEntry' => true,
                ],
            ], JSON_THROW_ON_ERROR);

            $filesystem->put($manifestPath, $firstManifest);

            $middleware = app(HandleInertiaRequests::class);
            $request = Request::create('/horizon');

            expect($middleware->version($request))->toBe(hash('xxh128', $firstManifest));

            $filesystem->put($manifestPath, $secondManifest);

            expect($middleware->version($request))->toBe(hash('xxh128', $secondManifest));
        } finally {
            $filesystem->deleteDirectory(public_path('vendor/horizon-new-dawn-version-test'));
        }
    });

    it('shares empty flash props for requests without a session', function (): void {
        $request = Request::create('/horizon');
        $shared = app(HandleInertiaRequests::class)->share($request);

        expect($request->hasSession())->toBeFalse()
            ->and(($shared['flash']['success'])())->toBeNull()
            ->and(($shared['flash']['error'])())->toBeNull();
    });

    it('shares string flash props for requests with a session', function (): void {
        $request = Request::create('/horizon');
        $session = app('session.store');
        $session->put('toast.success', 'Saved.');
        $session->put('toast.error', 'Try again.');
        $request->setLaravelSession($session);

        $shared = app(HandleInertiaRequests::class)->share($request);

        expect(($shared['flash']['success'])())->toBe('Saved.')
            ->and(($shared['flash']['error'])())->toBe('Try again.');
    });
});
