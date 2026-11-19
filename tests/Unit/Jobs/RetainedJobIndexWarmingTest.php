<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use NckRtl\HorizonNewDawn\Jobs\RetainedJobIndexWarming;

it('is not reported because another request is already building the index', function (): void {
    Exceptions::fake();

    report(new RetainedJobIndexWarming);

    Exceptions::assertNothingReported();
});
