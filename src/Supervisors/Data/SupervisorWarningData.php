<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Supervisors\Data;

use Spatie\LaravelData\Data;

final class SupervisorWarningData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
    ) {}
}
