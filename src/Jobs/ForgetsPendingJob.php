<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

interface ForgetsPendingJob
{
    /** @param array<int, string> $tags */
    public function forgetPending(string $id, array $tags): bool;
}
