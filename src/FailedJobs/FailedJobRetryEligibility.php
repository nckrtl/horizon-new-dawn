<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs;

use JsonException;

final class FailedJobRetryEligibility
{
    public function allows(object $job): bool
    {
        if ($this->isRetry($job->payload ?? null)) {
            return false;
        }

        return $this->allKnownRetriesFailed($job->retried_by ?? null);
    }

    private function isRetry(mixed $payload): bool
    {
        if (! is_string($payload) || $payload === '') {
            return true;
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

            return ! is_array($decoded) || array_key_exists('retry_of', $decoded);
        } catch (JsonException) {
            return true;
        }
    }

    private function allKnownRetriesFailed(mixed $retriedBy): bool
    {
        if ($retriedBy === null || $retriedBy === '' || $retriedBy === []) {
            return true;
        }

        if (is_string($retriedBy)) {
            try {
                $retriedBy = json_decode($retriedBy, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return false;
            }
        }

        if (! is_array($retriedBy)) {
            return false;
        }

        if ($retriedBy === []) {
            return true;
        }

        foreach ($retriedBy as $retry) {
            if (! is_array($retry) || ($retry['status'] ?? null) !== 'failed') {
                return false;
            }
        }

        return true;
    }
}
