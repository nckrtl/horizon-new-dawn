<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Supervisors;

use Illuminate\Support\Str;
use NckRtl\HorizonNewDawn\Instances\LocalInstanceName;

final class LocalSupervisor
{
    public static function matches(object $record, string $supervisor): bool
    {
        if (($record->name ?? null) !== $supervisor || ! str_contains($supervisor, ':')) {
            return false;
        }

        $masterFromName = Str::beforeLast($supervisor, ':');
        $recordMaster = $record->master ?? null;

        if ($recordMaster !== null && (! is_string($recordMaster) || $recordMaster !== $masterFromName)) {
            return false;
        }

        return LocalInstanceName::matches($recordMaster ?? $masterFromName);
    }
}
