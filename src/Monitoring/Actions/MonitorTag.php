<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Horizon\Jobs\MonitorTag as HorizonMonitorTag;

final readonly class MonitorTag
{
    public function __construct(private Dispatcher $bus) {}

    public function handle(string $tag): void
    {
        $this->bus->dispatch(new HorizonMonitorTag($tag));
    }
}
