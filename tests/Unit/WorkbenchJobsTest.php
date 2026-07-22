<?php

declare(strict_types=1);

use Illuminate\Bus\Batchable;
use Workbench\App\Jobs\FailingJob;
use Workbench\App\Jobs\SucceedingJob;

it('provides deterministic workbench queue jobs', function (): void {
    expect(fn () => (new SucceedingJob)->handle())->not->toThrow(Throwable::class)
        ->and(fn () => (new FailingJob)->handle())
        ->toThrow(RuntimeException::class, 'Intentional Workbench failure.');
});

it('can dispatch the deterministic workbench jobs in a batch', function (): void {
    expect(class_uses_recursive(SucceedingJob::class))->toContain(Batchable::class)
        ->and(class_uses_recursive(FailingJob::class))->toContain(Batchable::class);
});
