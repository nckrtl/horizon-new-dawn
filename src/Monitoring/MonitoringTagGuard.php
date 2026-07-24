<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring;

use InvalidArgumentException;
use Laravel\Horizon\Contracts\TagRepository;

final class MonitoringTagGuard
{
    /** @var list<string> */
    private const array RESERVED_NAMES = [
        'completed_jobs',
        'failed_jobs',
        'job_id',
        'last_snapshot_at',
        'masters',
        'measured_jobs',
        'measured_queues',
        'monitored_jobs',
        'monitoring',
        'pending_jobs',
        'recent_failed_jobs',
        'recent_jobs',
        'silenced_jobs',
        'supervisors',
    ];

    /** @var list<string> */
    private const array RESERVED_PREFIXES = [
        'commands:',
        'failed:',
        'job:',
        'master:',
        'metrics:',
        'monitor:',
        'notification:',
        'queue:',
        'snapshot:',
        'supervisor:',
    ];

    /** @var list<string> */
    private const array RESERVED_SUFFIXES = [
        ':orphans',
    ];

    public function isSafe(string $tag): bool
    {
        if ($tag === '' || in_array($tag, self::RESERVED_NAMES, true)) {
            return false;
        }

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($tag, $prefix)) {
                return false;
            }
        }

        foreach (self::RESERVED_SUFFIXES as $suffix) {
            if (str_ends_with($tag, $suffix)) {
                return false;
            }
        }

        return true;
    }

    public function ensureSafe(string $tag): void
    {
        if (! $this->isSafe($tag)) {
            throw new InvalidArgumentException('The tag conflicts with Horizon internal storage.');
        }
    }

    public function ensureMonitored(TagRepository $tags, string $tag): void
    {
        $this->ensureSafe($tag);

        if (! in_array($tag, $tags->monitoring(), true)) {
            throw new InvalidArgumentException('The tag is not currently monitored.');
        }
    }
}
