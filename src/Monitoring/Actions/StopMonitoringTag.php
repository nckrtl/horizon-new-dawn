<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Horizon\Jobs\StopMonitoringTag as HorizonStopMonitoringTag;

final readonly class StopMonitoringTag
{
    public function __construct(private Dispatcher $bus) {}

    public function handle(string $tag): void
    {
        $this->bus->dispatch(new HorizonStopMonitoringTag($tag));
    }
}
