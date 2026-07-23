<?php

declare(strict_types=1);

use NckRtl\HorizonNewDawn\Support\HorizonWorkCommandCompatibility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

describe('Horizon work command compatibility', function (): void {
    it('adds the idle stop option expected by newer Laravel workers', function (): void {
        $command = new Command('horizon:work');

        (new HorizonWorkCommandCompatibility(laravelExpectsIdleStopOption: true))->prepare($command);

        $option = $command->getDefinition()->getOption('stop-when-empty-for');

        expect($option->getDefault())->toBe(0)
            ->and($option->acceptValue())->toBeTrue()
            ->and($option->isValueOptional())->toBeTrue();
    });

    it('preserves an option already registered by Horizon', function (): void {
        $command = new Command('horizon:work');
        $existingOption = new InputOption(
            'stop-when-empty-for',
            null,
            InputOption::VALUE_OPTIONAL,
            'Existing Horizon option.',
            15,
        );
        $command->getDefinition()->addOption($existingOption);

        (new HorizonWorkCommandCompatibility(laravelExpectsIdleStopOption: true))->prepare($command);

        expect($command->getDefinition()->getOption('stop-when-empty-for'))
            ->toBe($existingOption);
    });

    it('does not add the option when Laravel does not expect it', function (): void {
        $command = new Command('horizon:work');

        (new HorizonWorkCommandCompatibility(laravelExpectsIdleStopOption: false))->prepare($command);

        expect($command->getDefinition()->hasOption('stop-when-empty-for'))->toBeFalse();
    });
});
