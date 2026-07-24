<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Horizon\Jobs\MonitorTag as HorizonMonitorTag;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringTagGuard;

final readonly class MonitorTag
{
    public function __construct(
        private Dispatcher $bus,
        private MonitoringTagGuard $guard,
    ) {}

    public function handle(string $tag): void
    {
        $this->guard->ensureSafe($tag);

        $this->bus->dispatch(new HorizonMonitorTag($tag));
    }
}
