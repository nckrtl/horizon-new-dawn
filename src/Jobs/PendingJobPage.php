<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Jobs;

final readonly class PendingJobPage
{
    /** @param array<int, string> $ids */
    public function __construct(
        public array $ids,
        public int|string|null $current,
        public ?string $next,
    ) {}
}
