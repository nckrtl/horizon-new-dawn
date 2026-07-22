<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Instances\Actions;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\InteractsWithTime;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\Terminate;
use NckRtl\HorizonNewDawn\Instances\LocalInstanceName;

final readonly class TerminateHorizon
{
    use InteractsWithTime;

    public function __construct(
        private MasterSupervisorRepository $masters,
        private HorizonCommandQueue $commands,
        private CacheRepository $cache,
    ) {}

    public function handle(): void
    {
        if (config('horizon.fast_termination')) {
            $this->cache->forever('horizon:terminate:wait', false);
        }

        foreach ($this->masters->all() as $master) {
            $name = is_object($master) ? ($master->name ?? null) : null;

            if (! is_string($name) || ! LocalInstanceName::matches($name)) {
                continue;
            }

            $this->commands->push(
                MasterSupervisor::commandQueueFor($name),
                Terminate::class,
                [],
            );
        }

        $this->cache->forever('illuminate:queue:restart', $this->currentTime());
    }
}
