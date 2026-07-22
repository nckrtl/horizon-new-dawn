<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support;

use Illuminate\Queue\QueueManager;
use LogicException;
use ReflectionClass;
use Spatie\LaravelData\Data;

final class FrameworkCapabilities extends Data
{
    public function __construct(
        public bool $queuePausing,
    ) {}

    public static function detect(): self
    {
        $queueManager = new ReflectionClass(QueueManager::class);

        return new self(
            queuePausing: $queueManager->hasMethod('pause')
                && $queueManager->hasMethod('pauseFor')
                && $queueManager->hasMethod('resume')
                && $queueManager->hasMethod('isPaused'),
        );
    }

    public function ensureQueuePausing(): void
    {
        if (! $this->queuePausing) {
            throw new LogicException('Queue pausing is not supported by the installed Laravel version.');
        }
    }
}
