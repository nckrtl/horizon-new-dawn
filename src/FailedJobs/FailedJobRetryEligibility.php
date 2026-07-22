<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\FailedJobs;

use JsonException;

final class FailedJobRetryEligibility
{
    public function allows(object $job): bool
    {
        if (! $this->hasValidPayload($job->payload ?? null)) {
            return false;
        }

        $retries = $this->retries($job->retried_by ?? null);

        return $retries !== null && $this->allRetriesFailed($retries);
    }

    public function allowsBulk(object $job): bool
    {
        if (! $this->hasValidPayload($job->payload ?? null)) {
            return false;
        }

        return $this->retries($job->retried_by ?? null) === [];
    }

    private function hasValidPayload(mixed $payload): bool
    {
        if (! is_string($payload) || $payload === '') {
            return false;
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded);
        } catch (JsonException) {
            return false;
        }
    }

    /** @return array<array-key, mixed>|null */
    private function retries(mixed $retriedBy): ?array
    {
        if ($retriedBy === null || $retriedBy === false || $retriedBy === '' || $retriedBy === []) {
            return [];
        }

        if (is_string($retriedBy)) {
            try {
                $retriedBy = json_decode($retriedBy, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        if (! is_array($retriedBy)) {
            return null;
        }

        return $retriedBy;
    }

    /** @param array<array-key, mixed> $retries */
    private function allRetriesFailed(array $retries): bool
    {
        foreach ($retries as $retry) {
            if (! is_array($retry) || ($retry['status'] ?? null) !== 'failed') {
                return false;
            }
        }

        return true;
    }
}
