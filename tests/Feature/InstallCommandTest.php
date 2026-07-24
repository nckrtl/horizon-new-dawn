<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

afterEach(function (): void {
    $filesystem = app(Filesystem::class);

    $filesystem->delete(config_path('horizon-new-dawn.php'));
    $filesystem->deleteDirectory(public_path('vendor/horizon-new-dawn'));
    $filesystem->deleteDirectory(public_path('vendor/horizon-new-dawn-install-test'));
    $filesystem->deleteDirectory(dirname(public_path()).'/horizon-new-dawn-outside-test');
});

it('publishes the package configuration and compiled assets', function (): void {
    $command = artisan('horizon-new-dawn:install', ['--force' => true]);

    if (! $command instanceof PendingCommand) {
        throw new RuntimeException('The install command did not return a pending command.');
    }

    $command
        ->expectsOutputToContain('Horizon New Dawn is ready')
        ->assertSuccessful()
        ->execute();

    $filesystem = app(Filesystem::class);
    $source = dirname(__DIR__, 2).'/dist/build';
    $destination = public_path('vendor/horizon-new-dawn/build');

    expect(config_path('horizon-new-dawn.php'))->toBeFile()
        ->and($destination.'/.vite/manifest.json')->toBeFile();

    foreach ($filesystem->allFiles($source) as $file) {
        expect($destination.'/'.$file->getRelativePathname())->toBeFile();
    }
});

it('rejects an asset path outside public before mutating configuration or assets', function (): void {
    $filesystem = app(Filesystem::class);
    $publishedConfig = config_path('horizon-new-dawn.php');
    $outsideDirectory = dirname(public_path()).'/horizon-new-dawn-outside-test';
    $outsideSentinel = $outsideDirectory.'/keep.txt';
    $consumerConfig = "<?php\n\nreturn ['consumer' => true];\n";

    $filesystem->ensureDirectoryExists(dirname($publishedConfig));
    $filesystem->put($publishedConfig, $consumerConfig);
    $filesystem->ensureDirectoryExists($outsideDirectory);
    $filesystem->put($outsideSentinel, 'keep');
    config()->set('horizon-new-dawn.assets_path', '../horizon-new-dawn-outside-test');

    expect(fn (): int => Artisan::call('horizon-new-dawn:install', ['--force' => true]))
        ->toThrow(RuntimeException::class, 'relative path within the public directory')
        ->and($filesystem->get($publishedConfig))->toBe($consumerConfig)
        ->and($filesystem->get($outsideSentinel))->toBe('keep');
});

it('preserves published consumer configuration when force refreshing assets', function (): void {
    $filesystem = app(Filesystem::class);
    $publishedConfig = config_path('horizon-new-dawn.php');
    $assetsPath = 'vendor/horizon-new-dawn-install-test/custom-build';
    $consumerConfig = <<<PHP
<?php

declare(strict_types=1);

return [
    'assets_path' => '{$assetsPath}',
    'poll_interval' => 1234,
];
PHP;

    $filesystem->ensureDirectoryExists(dirname($publishedConfig));
    $filesystem->put($publishedConfig, $consumerConfig);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install', ['--force' => true]))->toBe(0)
        ->and($filesystem->get($publishedConfig))->toBe($consumerConfig)
        ->and(public_path($assetsPath.'/.vite/manifest.json'))->toBeFile();
});

it('keeps old content hashed assets during a forced refresh', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);
    $oldAsset = $destination.'/assets/app-old-content-hash.js';

    $filesystem->ensureDirectoryExists(dirname($oldAsset));
    $filesystem->put($oldAsset, 'old asset');
    $filesystem->ensureDirectoryExists($destination.'/.vite');
    $filesystem->put($destination.'/.vite/manifest.json', '{"old":true}');
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install', ['--force' => true]))->toBe(0)
        ->and($oldAsset)->toBeFile()
        ->and($filesystem->get($oldAsset))->toBe('old asset')
        ->and($filesystem->get($destination.'/.vite/manifest.json'))->not->toBe('{"old":true}');
});

it('keeps the live manifest valid when publishing a staged refresh fails', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);
    $manifestPath = $destination.'/.vite/manifest.json';
    $oldManifest = json_encode([
        'resources/js/app.tsx' => [
            'file' => 'assets/app-old.js',
            'css' => [],
        ],
    ], JSON_THROW_ON_ERROR);

    $filesystem->ensureDirectoryExists($destination.'/.vite');
    $filesystem->ensureDirectoryExists($destination.'/assets');
    $filesystem->put($manifestPath, $oldManifest);
    $filesystem->put($destination.'/assets/app-old.js', 'old asset');
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    app()->instance(Filesystem::class, new class extends Filesystem
    {
        public function copy($path, $target): bool
        {
            if (
                ! str_contains((string) $path, '/dist/build/')
                && str_contains((string) $target, '/horizon-new-dawn-install-test/build/assets/')
            ) {
                return false;
            }

            return parent::copy($path, $target);
        }
    });

    expect(fn (): int => Artisan::call('horizon-new-dawn:install', ['--force' => true]))
        ->toThrow(RuntimeException::class, 'Unable to publish Horizon New Dawn asset')
        ->and($manifestPath)->toBeFile()
        ->and((new Filesystem)->get($manifestPath))->toBe($oldManifest)
        ->and($destination.'/assets/app-old.js')->toBeFile();
});

it('repairs an empty published assets directory without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    $filesystem->ensureDirectoryExists($destination);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and($destination.'/.vite/manifest.json')->toBeFile()
        ->and($destination.'/favicon.svg')->toBeFile();
});

it('repairs a top-level list manifest without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishBrokenPublication($filesystem, $destination, []);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination))->toHaveKey('resources/js/app.tsx');
});

it('repairs a publication with a missing referenced chunk without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishBrokenPublication($filesystem, $destination, [
        'resources/js/app.tsx' => [
            'file' => 'assets/app-missing.js',
        ],
    ], [
        'missing' => ['assets/app-missing.js'],
    ]);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and($destination.'/assets/app-missing.js')->not->toBeFile()
        ->and(installedManifest($destination)['resources/js/app.tsx']['file'] ?? null)->not->toBe('assets/app-missing.js');
});

it('repairs a malformed published manifest without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishCompletePublication($filesystem, $destination);
    $filesystem->put($destination.'/.vite/manifest.json', '{invalid');
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and($destination.'/.vite/manifest.json')->toBeFile()
        ->and(installedManifest($destination))->toHaveKey('resources/js/app.tsx');
});

it('repairs a published manifest that is missing the entrypoint without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishManifestFixture($filesystem, $destination, [
        'resources/js/other.tsx' => [
            'file' => 'assets/app-other.js',
            'css' => [],
            'assets' => [],
        ],
    ], ['assets/app-other.js']);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination))->toHaveKey('resources/js/app.tsx');
});

it('repairs a publication that is missing the favicon without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);
    publishCompletePublication($filesystem, $destination, withFavicon: false);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect($destination.'/favicon.svg')->not->toBeFile()
        ->and(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and($destination.'/favicon.svg')->toBeFile();
});

it('repairs a publication with an invalid file path without force', function (string $path): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishBrokenPublication($filesystem, $destination, [
        'resources/js/app.tsx' => [
            'file' => $path,
        ],
    ]);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination)['resources/js/app.tsx']['file'] ?? null)->not->toBe($path);
})->with([
    'empty file' => [''],
    'absolute file' => ['/escape.js'],
    'traversal file' => ['../escape.js'],
]);

it('repairs a publication with an invalid asset collection path without force', function (string $collection, string $path): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishBrokenPublication($filesystem, $destination, [
        'resources/js/app.tsx' => [
            $collection => [$path],
        ],
    ]);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination)['resources/js/app.tsx'][$collection] ?? [])->not->toContain($path);
})->with([
    'missing css' => ['css', 'assets/missing.css'],
    'unsafe css' => ['css', '../escape.css'],
    'missing asset' => ['assets', 'assets/missing.svg'],
    'unsafe asset' => ['assets', '/escape.svg'],
]);

it('repairs a publication with a dangling import without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishBrokenPublication($filesystem, $destination, [
        'resources/js/app.tsx' => [
            'imports' => ['missing-chunk'],
        ],
    ]);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination)['resources/js/app.tsx']['imports'] ?? [])->not->toContain('missing-chunk');
});

it('repairs a publication with a dangling dynamic import without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);

    publishBrokenPublication($filesystem, $destination, [
        'resources/js/app.tsx' => [
            'dynamicImports' => ['missing-dynamic-chunk'],
        ],
    ]);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination)['resources/js/app.tsx']['dynamicImports'] ?? [])->not->toContain('missing-dynamic-chunk');
});

it('preserves a complete older publication without force', function (): void {
    $filesystem = app(Filesystem::class);
    $assetsPath = 'vendor/horizon-new-dawn-install-test/build';
    $destination = public_path($assetsPath);
    $manifest = [
        'resources/js/app.tsx' => [
            'file' => 'assets/app-older.js',
            'css' => ['assets/app-older.css'],
            'assets' => ['assets/logo-older.svg'],
        ],
    ];

    publishManifestFixture($filesystem, $destination, $manifest, [
        'assets/app-older.js',
        'assets/app-older.css',
        'assets/logo-older.svg',
    ], true);
    config()->set('horizon-new-dawn.assets_path', $assetsPath);

    expect(Artisan::call('horizon-new-dawn:install'))->toBe(0)
        ->and(installedManifest($destination))->toBe($manifest)
        ->and($filesystem->get($destination.'/assets/app-older.js'))->toBe('fixture:assets/app-older.js');
});

/**
 * @param  array<int|string, mixed>  $overrideManifest
 * @param  array{missing?: list<string>, override?: array<string, string>}  $mutations
 */
function publishBrokenPublication(Filesystem $filesystem, string $destination, array $overrideManifest, array $mutations = [], bool $withFavicon = true): void
{
    $baseManifest = completePublicationManifest();
    $manifest = array_is_list($overrideManifest)
        ? $overrideManifest
        : array_replace_recursive($baseManifest, $overrideManifest);

    $files = completePublicationFiles();

    foreach ($mutations['missing'] ?? [] as $missingPath) {
        unset($files[$missingPath]);
    }

    foreach ($mutations['override'] ?? [] as $path => $contents) {
        $files[$path] = $contents;
    }

    publishManifestFixture($filesystem, $destination, $manifest, array_keys($files), $withFavicon);

    foreach ($files as $path => $contents) {
        $filesystem->put($destination.'/'.$path, $contents);
    }
}

function publishCompletePublication(Filesystem $filesystem, string $destination, bool $withFavicon = true): void
{
    publishManifestFixture(
        $filesystem,
        $destination,
        completePublicationManifest(),
        array_keys(completePublicationFiles()),
        $withFavicon,
    );

    foreach (completePublicationFiles() as $path => $contents) {
        $filesystem->put($destination.'/'.$path, $contents);
    }
}

/**
 * @param  array<string, array<string, list<string>|string>>|list<mixed>  $manifest
 * @param  list<string>  $files
 */
function publishManifestFixture(
    Filesystem $filesystem,
    string $destination,
    array $manifest,
    array $files = [],
    bool $withFavicon = true,
): void {
    $filesystem->ensureDirectoryExists($destination.'/.vite');
    $filesystem->put($destination.'/.vite/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

    foreach ($files as $file) {
        $filesystem->ensureDirectoryExists(dirname($destination.'/'.$file));

        if (! $filesystem->exists($destination.'/'.$file)) {
            $filesystem->put($destination.'/'.$file, 'fixture:'.$file);
        }
    }

    if ($withFavicon) {
        $filesystem->put($destination.'/favicon.svg', '<svg />');
    }
}

/** @return array<string, array{file: string, css: list<string>, assets: list<string>, imports?: list<string>, dynamicImports?: list<string>}> */
function completePublicationManifest(): array
{
    return [
        'resources/js/app.tsx' => [
            'file' => 'assets/app.js',
            'css' => ['assets/app.css'],
            'assets' => ['assets/logo.svg'],
            'imports' => ['resources/js/chunk.ts'],
            'dynamicImports' => ['resources/js/dynamic.ts'],
        ],
        'resources/js/chunk.ts' => [
            'file' => 'assets/chunk.js',
            'css' => [],
            'assets' => [],
        ],
        'resources/js/dynamic.ts' => [
            'file' => 'assets/dynamic.js',
            'css' => [],
            'assets' => [],
        ],
    ];
}

/** @return array<string, string> */
function completePublicationFiles(): array
{
    return [
        'assets/app.js' => 'fixture:assets/app.js',
        'assets/app.css' => 'fixture:assets/app.css',
        'assets/logo.svg' => 'fixture:assets/logo.svg',
        'assets/chunk.js' => 'fixture:assets/chunk.js',
        'assets/dynamic.js' => 'fixture:assets/dynamic.js',
    ];
}

/** @return array<string, array<string, list<string>|string>> */
function installedManifest(string $buildDirectory): array
{
    $manifest = json_decode(
        app(Filesystem::class)->get($buildDirectory.'/.vite/manifest.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    if (! is_array($manifest)) {
        throw new RuntimeException('The installed Vite manifest is invalid.');
    }

    return $manifest;
}
