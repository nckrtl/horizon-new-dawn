<?php

declare(strict_types=1);

use Illuminate\Cache\CacheManager;
use NckRtl\HorizonNewDawn\Support\FrameworkCapabilities;
use NckRtl\HorizonNewDawn\Tests\BrowserTestCase;
use NckRtl\HorizonNewDawn\Tests\TestCase;
use PHPUnit\Framework\SkippedWithMessageException;

require_once __DIR__.'/Support/DashboardMocks.php';
require_once __DIR__.'/Support/BrowserPageFixtures.php';
require_once __DIR__.'/Support/HorizonBatches.php';
require_once __DIR__.'/Support/HorizonJobs.php';
require_once __DIR__.'/Support/PendingJobOrdering.php';

pest()->extend(TestCase::class)->in('Compatibility', 'Feature', 'Unit');
pest()->extend(BrowserTestCase::class)->in('Browser');

function queuePausingIsSupported(): bool
{
    return FrameworkCapabilities::detect()->queuePausing;
}

function requireQueuePausing(): void
{
    if (! queuePausingIsSupported()) {
        throw new SkippedWithMessageException('Queue pausing is unavailable on this Laravel version.');
    }
}

function requireConfigurableCacheUnserialization(): void
{
    if (! (new ReflectionClass(CacheManager::class))->hasMethod('getSerializableClasses')) {
        throw new SkippedWithMessageException(
            'Configurable cache unserialization is unavailable on this Laravel version.',
        );
    }
}
