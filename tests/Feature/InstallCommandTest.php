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
