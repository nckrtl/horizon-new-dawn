<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Supervisors\Data;

use Spatie\LaravelData\Data;

final class SupervisorDetailsResultData extends Data
{
    public function __construct(
        public readonly bool $available,
        public readonly ?SupervisorDetailsData $supervisor,
        public readonly ?string $message,
    ) {}
}
