<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Assets;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final readonly class AssetPath
{
    private const string INVALID_RELATIVE_PATH = 'The horizon-new-dawn.assets_path configuration value must be a non-empty relative path within the public directory.';

    public function __construct(
        private ConfigRepository $config,
        private Application $application,
    ) {}

    public function relative(): string
    {
        $path = $this->config->get('horizon-new-dawn.assets_path');

        if (! is_string($path) || $path === '' || trim($path) !== $path) {
            throw new RuntimeException(self::INVALID_RELATIVE_PATH);
        }

        if (
            str_starts_with($path, '/')
            || str_contains($path, '\\')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new RuntimeException(self::INVALID_RELATIVE_PATH);
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException(self::INVALID_RELATIVE_PATH);
            }
        }

        return $path;
    }

    public function absolute(): string
    {
        $relativePath = $this->relative();
        $publicPath = realpath($this->application->publicPath());

        if (! is_string($publicPath)) {
            throw new RuntimeException('The public directory could not be resolved for Horizon New Dawn assets.');
        }

        $currentPath = $publicPath;

        foreach (explode('/', $relativePath) as $segment) {
            $currentPath .= DIRECTORY_SEPARATOR.$segment;

            if (! file_exists($currentPath) && ! is_link($currentPath)) {
                continue;
            }

            $resolvedPath = realpath($currentPath);

            if (
                ! is_string($resolvedPath)
                || $resolvedPath === $publicPath
                || ! str_starts_with($resolvedPath, $publicPath.DIRECTORY_SEPARATOR)
            ) {
                throw new RuntimeException('The horizon-new-dawn.assets_path configuration value must resolve within the public directory.');
            }
        }

        return $publicPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    public function manifest(): string
    {
        return $this->absolute().DIRECTORY_SEPARATOR.'.vite'.DIRECTORY_SEPARATOR.'manifest.json';
    }
}
