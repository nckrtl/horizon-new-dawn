<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Tests\Support;

final class HorizonJob
{
    public string $connection = 'redis';

    public string $queue = 'default';

    public string $name = 'App\\Jobs\\ImportFeed';

    public string $status = 'completed';

    public string $payload;

    public string $reserved_at = '1784281001.25';

    public ?string $updated_at = null;

    public ?int $delay = null;

    public ?string $completed_at = '1784281002.75';

    public ?string $failed_at = null;

    public string $exception = 'sensitive trace';

    public ?string $context = '{"secret":"context"}';

    public ?string $retried_by = null;

    public function __construct(
        public int $index,
        public string $id,
    ) {
        $this->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'pushedAt' => 1_784_281_000.25,
            'tags' => ['tenant:1', 'import'],
            'data' => [
                'commandName' => 'App\\Jobs\\ImportFeed',
                'command' => 'serialized-secret-command',
            ],
        ], JSON_THROW_ON_ERROR);
    }
}

function horizonJob(int $index, string $id = 'job-1'): HorizonJob
{
    return new HorizonJob($index, $id);
}
