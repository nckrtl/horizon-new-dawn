<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use NckRtl\HorizonNewDawn\Assets\AssetPath;
use RuntimeException;

final class InstallCommand extends Command
{
    protected $signature = 'horizon-new-dawn:install
        {--force : Refresh previously published assets}';

    protected $description = 'Publish the Horizon New Dawn configuration and compiled assets';

    public function handle(Filesystem $filesystem, AssetPath $assetPath): int
    {
        $force = (bool) $this->option('force');
        $packageRoot = dirname(__DIR__, 2);
        $assetsDestination = $assetPath->absolute();

        $this->publishFile(
            $filesystem,
            $packageRoot.'/config/horizon-new-dawn.php',
            config_path('horizon-new-dawn.php'),
        );

        $this->publishAssets(
            $filesystem,
            $packageRoot.'/dist/build',
            $assetsDestination,
            $force,
        );

        $this->components->info('Horizon New Dawn is ready.');

        return self::SUCCESS;
    }

    private function publishFile(
        Filesystem $filesystem,
        string $source,
        string $destination,
    ): void {
        if ($filesystem->exists($destination)) {
            return;
        }

        $filesystem->ensureDirectoryExists(dirname($destination));

        if (! $filesystem->copy($source, $destination)) {
            throw new RuntimeException("Unable to publish {$destination}.");
        }
    }

    private function publishAssets(
        Filesystem $filesystem,
        string $source,
        string $destination,
        bool $force,
    ): void {
        if (! $force && $this->hasCompletePublishedAssets($filesystem, $destination)) {
            return;
        }

        $filesystem->ensureDirectoryExists(dirname($destination));
        $stagingDirectory = dirname($destination).'/.'.basename($destination).'-'.Str::random(20).'.tmp';

        try {
            if (! $filesystem->copyDirectory($source, $stagingDirectory)) {
                throw new RuntimeException('Unable to stage Horizon New Dawn assets.');
            }

            $stagedManifest = $stagingDirectory.'/.vite/manifest.json';

            if (! $filesystem->isFile($stagedManifest)) {
                throw new RuntimeException('Unable to stage Horizon New Dawn assets because the manifest is missing.');
            }

            if (! $filesystem->isDirectory($destination)) {
                if (! $filesystem->moveDirectory($stagingDirectory, $destination)) {
                    throw new RuntimeException("Unable to publish {$destination}.");
                }

                return;
            }

            $this->mergeStagedAssets($filesystem, $stagingDirectory, $destination);
        } finally {
            if ($filesystem->isDirectory($stagingDirectory)) {
                $filesystem->deleteDirectory($stagingDirectory);
            }
        }
    }

    private function hasCompletePublishedAssets(Filesystem $filesystem, string $destination): bool
    {
        if (! $filesystem->isDirectory($destination)) {
            return false;
        }

        if (! $filesystem->isFile($destination.'/favicon.svg')) {
            return false;
        }

        $manifestPath = $destination.'/.vite/manifest.json';

        if (! $filesystem->isFile($manifestPath)) {
            return false;
        }

        try {
            $manifest = json_decode($filesystem->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($manifest) || array_is_list($manifest) || ! array_key_exists('resources/js/app.tsx', $manifest)) {
            return false;
        }

        foreach ($manifest as $key => $entry) {
            if (! is_string($key) || ! is_array($entry)) {
                return false;
            }

            if (! $this->publishedAssetExists($filesystem, $destination, $entry['file'] ?? null)) {
                return false;
            }

            foreach (['css', 'assets'] as $collection) {
                $paths = $entry[$collection] ?? [];

                if (! is_array($paths)) {
                    return false;
                }

                foreach ($paths as $path) {
                    if (! $this->publishedAssetExists($filesystem, $destination, $path)) {
                        return false;
                    }
                }
            }

            foreach (['imports', 'dynamicImports'] as $collection) {
                $imports = $entry[$collection] ?? [];

                if (! is_array($imports)) {
                    return false;
                }

                foreach ($imports as $import) {
                    if (! is_string($import) || ! array_key_exists($import, $manifest)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function publishedAssetExists(Filesystem $filesystem, string $destination, mixed $path): bool
    {
        if (! is_string($path)) {
            return false;
        }

        $relativePath = $this->normalizeRelativeAssetPath($path);

        if ($relativePath === null) {
            return false;
        }

        return $filesystem->isFile($destination.'/'.$relativePath);
    }

    private function normalizeRelativeAssetPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '' || Str::startsWith($normalized, ['/']) || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                return null;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function mergeStagedAssets(
        Filesystem $filesystem,
        string $stagingDirectory,
        string $destination,
    ): void {
        $manifest = '.vite/manifest.json';

        foreach ($filesystem->allFiles($stagingDirectory) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if ($relativePath === $manifest) {
                continue;
            }

            $this->publishAssetFile(
                $filesystem,
                $file->getPathname(),
                $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
            );
        }

        $this->publishAssetFile(
            $filesystem,
            $stagingDirectory.'/'.$manifest,
            $destination.'/'.$manifest,
        );
    }

    private function publishAssetFile(
        Filesystem $filesystem,
        string $source,
        string $destination,
    ): void {
        $filesystem->ensureDirectoryExists(dirname($destination));
        $temporaryDestination = $destination.'.'.Str::random(20).'.tmp';

        try {
            if (! $filesystem->copy($source, $temporaryDestination)) {
                throw new RuntimeException("Unable to publish Horizon New Dawn asset {$destination}.");
            }

            if (! $filesystem->move($temporaryDestination, $destination)) {
                throw new RuntimeException("Unable to publish Horizon New Dawn asset {$destination}.");
            }
        } finally {
            if ($filesystem->exists($temporaryDestination)) {
                $filesystem->delete($temporaryDestination);
            }
        }
    }
}
