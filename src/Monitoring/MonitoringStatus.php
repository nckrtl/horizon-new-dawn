<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Monitoring;

enum MonitoringStatus: string
{
    case Jobs = 'jobs';
    case Failed = 'failed';

    public function repositoryTag(string $tag): string
    {
        return $this === self::Failed ? "failed:{$tag}" : $tag;
    }
}
