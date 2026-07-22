<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Assets;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\UrlGenerator;
use JsonException;
use RuntimeException;

final readonly class AssetManifest
{
    private const string ENTRY = 'resources/js/app.tsx';

    public function __construct(
        private Filesystem $filesystem,
        private AssetPath $assetPath,
        private UrlGenerator $url,
    ) {}

    public function script(): string
    {
        return $this->assetUrl($this->entry()['file']);
    }

    public function favicon(): string
    {
        return $this->assetUrl('favicon.svg');
    }

    /** @return list<string> */
    public function styles(): array
    {
        return array_map(
            fn (string $path): string => $this->assetUrl($path),
            $this->entry()['css'],
        );
    }

    public function version(): string
    {
        $this->entry();

        return hash('xxh128', $this->filesystem->get($this->manifestPath()));
    }

    /** @return array{file: string, css: list<string>} */
    private function entry(): array
    {
        $manifestPath = $this->manifestPath();

        if (! $this->filesystem->exists($manifestPath)) {
            throw new RuntimeException(
                'Horizon New Dawn assets are not published. Run `php artisan horizon-new-dawn:install`.',
            );
        }

        try {
            $manifest = json_decode(
                $this->filesystem->get($manifestPath),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'The published Horizon New Dawn asset manifest is invalid. Run `php artisan horizon-new-dawn:install --force`.',
                previous: $exception,
            );
        }

        if (! is_array($manifest)) {
            throw new RuntimeException('The published Horizon New Dawn asset manifest is invalid.');
        }

        $entry = $manifest[self::ENTRY] ?? null;

        if (! is_array($entry) || ! is_string($entry['file'] ?? null)) {
            throw new RuntimeException('The published Horizon New Dawn asset manifest has no application entry.');
        }

        $styles = $entry['css'] ?? [];

        if (! is_array($styles)) {
            throw new RuntimeException('The published Horizon New Dawn asset manifest has invalid styles.');
        }

        foreach ($styles as $style) {
            if (! is_string($style)) {
                throw new RuntimeException('The published Horizon New Dawn asset manifest has invalid styles.');
            }
        }

        return [
            'file' => $entry['file'],
            'css' => array_values($styles),
        ];
    }

    private function manifestPath(): string
    {
        return $this->assetPath->manifest();
    }

    private function assetUrl(string $path): string
    {
        return $this->url->asset($this->assetPath->relative().'/'.ltrim($path, '/'));
    }
}
