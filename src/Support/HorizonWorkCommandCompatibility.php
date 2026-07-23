<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Support;

use Illuminate\Queue\Console\WorkCommand as LaravelWorkCommand;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

final readonly class HorizonWorkCommandCompatibility
{
    public function __construct(
        private ?bool $laravelExpectsIdleStopOption = null,
    ) {}

    public function prepare(Command $command): void
    {
        if (! $this->laravelExpectsIdleStopOption() || $command->getDefinition()->hasOption('stop-when-empty-for')) {
            return;
        }

        $command->getDefinition()->addOption(new InputOption(
            'stop-when-empty-for',
            null,
            InputOption::VALUE_OPTIONAL,
            'Stop when no jobs have been processed for the given number of seconds',
            0,
        ));
    }

    private function laravelExpectsIdleStopOption(): bool
    {
        if ($this->laravelExpectsIdleStopOption !== null) {
            return $this->laravelExpectsIdleStopOption;
        }

        $signature = (new ReflectionClass(LaravelWorkCommand::class))
            ->getDefaultProperties()['signature'] ?? null;

        return is_string($signature) && str_contains($signature, '--stop-when-empty-for');
    }
}
