<?php

declare(strict_types=1);

use NckRtl\HorizonNewDawn\HorizonNewDawnServiceProvider;

describe('package boot', function (): void {
    it('boots the package configuration', function (): void {
        expect(app()->getProvider(HorizonNewDawnServiceProvider::class))->not->toBeNull()
            ->and(app()->environment())->toBe('local')
            ->and(config('horizon-new-dawn.poll_interval'))->toBe(5000)
            ->and(config('horizon-new-dawn.recent_failures_limit'))->toBe(5);
    });
});
