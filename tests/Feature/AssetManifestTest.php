<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use NckRtl\HorizonNewDawn\Assets\AssetManifest;
use NckRtl\HorizonNewDawn\Assets\AssetPath;

beforeEach(function (): void {
    config()->set('horizon-new-dawn.assets_path', 'vendor/horizon-new-dawn-test/build');

    $filesystem = app(Filesystem::class);
    $filesystem->deleteDirectory(public_path('vendor/horizon-new-dawn-test'));
});

afterEach(function (): void {
    $filesystem = app(Filesystem::class);
    $symlink = public_path('vendor/horizon-new-dawn-assets-link');

    $filesystem->deleteDirectory(public_path('vendor/horizon-new-dawn-test'));
    $filesystem->deleteDirectory(dirname(public_path()).'/horizon-new-dawn-assets-outside');

    if (is_link($symlink)) {
        $filesystem->delete($symlink);
    }
});

it('resolves hashed entry assets from the published Vite manifest', function (): void {
    $filesystem = app(Filesystem::class);
    $manifestDirectory = public_path('vendor/horizon-new-dawn-test/build/.vite');

    $filesystem->ensureDirectoryExists($manifestDirectory);
    $filesystem->put($manifestDirectory.'/manifest.json', json_encode([
        'resources/js/app.tsx' => [
            'file' => 'assets/app-abc123.js',
            'css' => ['assets/app-def456.css'],
            'isEntry' => true,
        ],
    ], JSON_THROW_ON_ERROR));

    $manifest = app(AssetManifest::class);

    expect($manifest->script())->toBe(url('/vendor/horizon-new-dawn-test/build/assets/app-abc123.js'))
        ->and($manifest->styles())->toBe([
            url('/vendor/horizon-new-dawn-test/build/assets/app-def456.css'),
        ]);
});

it('explains how to repair a missing published manifest', function (): void {
    expect(fn (): string => app(AssetManifest::class)->script())
        ->toThrow(RuntimeException::class, 'php artisan horizon-new-dawn:install');
});

it('rejects unsafe configured asset paths', function (mixed $path): void {
    config()->set('horizon-new-dawn.assets_path', $path);

    expect(fn (): string => app(AssetPath::class)->absolute())
        ->toThrow(RuntimeException::class, 'relative path within the public directory');
})->with([
    'null' => null,
    'empty' => '',
    'public root' => '.',
    'parent traversal' => '../storage/horizon-new-dawn',
    'nested traversal' => 'vendor/../storage/horizon-new-dawn',
    'absolute path' => '/tmp/horizon-new-dawn',
    'windows absolute path' => 'C:\\temp\\horizon-new-dawn',
    'backslash traversal' => '..\\storage\\horizon-new-dawn',
]);

it('rejects an asset path whose existing symlink escapes public', function (): void {
    $filesystem = app(Filesystem::class);
    $outsideDirectory = dirname(public_path()).'/horizon-new-dawn-assets-outside';
    $symlink = public_path('vendor/horizon-new-dawn-assets-link');

    $filesystem->ensureDirectoryExists($outsideDirectory);
    $filesystem->ensureDirectoryExists(dirname($symlink));
    $filesystem->link($outsideDirectory, $symlink);
    config()->set('horizon-new-dawn.assets_path', 'vendor/horizon-new-dawn-assets-link/build');

    expect(fn (): string => app(AssetPath::class)->absolute())
        ->toThrow(RuntimeException::class, 'resolve within the public directory');
});

it('ships every file referenced by the production Vite manifest', function (): void {
    $buildPath = dirname(__DIR__, 2).'/dist/build';
    $manifest = json_decode(
        app(Filesystem::class)->get($buildPath.'/.vite/manifest.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    if (! is_array($manifest)) {
        throw new RuntimeException('The production Vite manifest is invalid.');
    }

    foreach ($manifest as $entry) {
        if (! is_array($entry)) {
            throw new RuntimeException('The production Vite manifest contains an invalid entry.');
        }

        $file = $entry['file'] ?? null;

        if (! is_string($file)) {
            throw new RuntimeException('A production Vite manifest entry has no file.');
        }

        expect($buildPath.'/'.$file)->toBeFile();

        foreach (['css', 'assets'] as $collection) {
            $paths = $entry[$collection] ?? [];

            if (! is_array($paths)) {
                throw new RuntimeException("A production Vite manifest entry has invalid {$collection}.");
            }

            foreach ($paths as $path) {
                if (! is_string($path)) {
                    throw new RuntimeException("A production Vite manifest entry has an invalid {$collection} path.");
                }

                expect($buildPath.'/'.$path)->toBeFile();
            }
        }

        foreach (['imports', 'dynamicImports'] as $collection) {
            $imports = $entry[$collection] ?? [];

            if (! is_array($imports)) {
                throw new RuntimeException("A production Vite manifest entry has invalid {$collection}.");
            }

            foreach ($imports as $import) {
                if (! is_string($import)) {
                    throw new RuntimeException("A production Vite manifest entry has an invalid {$collection} key.");
                }

                expect($manifest)->toHaveKey($import);
            }
        }
    }
});
