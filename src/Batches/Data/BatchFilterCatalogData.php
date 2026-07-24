<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Batches\Data;

use Spatie\LaravelData\Data;

final class BatchFilterCatalogData extends Data
{
    /** @param list<string> $queues
     * @param  list<string>  $connections
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $queues,
        public readonly array $connections,
    ) {}
}
