<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
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
        if ($filesystem->isDirectory($destination) && ! $force) {
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
