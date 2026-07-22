<?php

declare(strict_types=1);

use NckRtl\HorizonNewDawn\Support\Scrolling\HorizonScrollMetadata;

describe('HorizonScrollMetadata', function (): void {
    it('adapts Horizon indexes to Inertia scroll metadata', function (): void {
        $metadata = new HorizonScrollMetadata(
            pageName: 'starting_at',
            previous: null,
            next: 49,
            current: -1,
        );

        expect($metadata->getPageName())->toBe('starting_at')
            ->and($metadata->getPreviousPage())->toBeNull()
            ->and($metadata->getNextPage())->toBe(49)
            ->and($metadata->getCurrentPage())->toBe(-1);
    });
});
