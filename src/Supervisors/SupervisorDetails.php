<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Supervisors;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\SupervisorRepository;
use NckRtl\HorizonNewDawn\Supervisors\Data\SupervisorDetailsData;
use NckRtl\HorizonNewDawn\Supervisors\Data\SupervisorDetailsResultData;
use NckRtl\HorizonNewDawn\Supervisors\Data\SupervisorWarningData;
use Throwable;

final readonly class SupervisorDetails
{
    public function __construct(
        private SupervisorRepository $supervisors,
        private ConfigRepository $config,
    ) {}

    public function find(string $name): SupervisorDetailsResultData
    {
        try {
            $supervisor = $this->activeSupervisor($name);

            if (! is_object($supervisor)) {
                return new SupervisorDetailsResultData(true, null, null);
            }

            return new SupervisorDetailsResultData(
                available: true,
                supervisor: $this->normalize($supervisor),
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new SupervisorDetailsResultData(
                available: false,
                supervisor: null,
                message: 'Horizon supervisor details are currently unavailable.',
            );
        }
    }

    private function activeSupervisor(string $name): mixed
    {
        return $this->supervisors->find($name);
    }

    private function normalize(object $supervisor): SupervisorDetailsData
    {
        $id = is_string($supervisor->name ?? null) ? $supervisor->name : '';
        $master = is_string($supervisor->master ?? null) && $supervisor->master !== ''
            ? $supervisor->master
            : Str::beforeLast($id, ':');
        $master = $master !== '' ? $master : 'Horizon';
        $options = is_array($supervisor->options ?? null) ? $supervisor->options : [];
        $processes = is_array($supervisor->processes ?? null) ? $supervisor->processes : [];
        $connection = $this->stringOption($options, 'connection')
            ?? $this->connectionFromProcesses($processes)
            ?? 'default';
        $timeout = $this->intOption($options, 'timeout');
        $retryAfter = $this->retryAfter($connection);

        return new SupervisorDetailsData(
            id: $id,
            name: Str::afterLast($id, ':'),
            master: $master,
            pid: is_numeric($supervisor->pid ?? null) ? (int) $supervisor->pid : null,
            status: is_string($supervisor->status ?? null) ? $supervisor->status : 'inactive',
            connection: $connection,
            queues: $this->queues($options, $processes),
            processes: $this->processCount($processes),
            balance: $this->stringOption($options, 'balance'),
            autoScalingStrategy: $this->stringOption($options, 'autoScalingStrategy'),
            minProcesses: $this->intOption($options, 'minProcesses'),
            maxProcesses: $this->intOption($options, 'maxProcesses'),
            balanceCooldown: $this->intOption($options, 'balanceCooldown'),
            balanceMaxShift: $this->intOption($options, 'balanceMaxShift'),
            memory: $this->intOption($options, 'memory'),
            timeout: $timeout,
            retryAfter: $retryAfter,
            maxTries: $this->intOption($options, 'maxTries'),
            backoff: $this->backoffOption($options),
            maxJobs: $this->intOption($options, 'maxJobs'),
            maxTime: $this->intOption($options, 'maxTime'),
            sleep: $this->intOption($options, 'sleep'),
            rest: $this->intOption($options, 'rest'),
            force: $this->boolOption($options, 'force'),
            nice: $this->intOption($options, 'nice'),
            warnings: $this->warnings($timeout, $retryAfter),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $processes
     * @return array<int, string>
     */
    private function queues(array $options, array $processes): array
    {
        $queue = $options['queue'] ?? null;

        if (is_string($queue) && trim($queue) !== '') {
            return array_values(array_filter(
                array_map(trim(...), explode(',', $queue)),
                static fn (string $name): bool => $name !== '',
            ));
        }

        if (is_array($queue)) {
            return array_values(array_filter(
                $queue,
                static fn (mixed $name): bool => is_string($name) && $name !== '',
            ));
        }

        $queues = [];

        foreach (array_keys($processes) as $descriptor) {
            $name = Str::contains($descriptor, ':')
                ? Str::after($descriptor, ':')
                : $descriptor;

            foreach (explode(',', $name) as $queueName) {
                $queueName = trim($queueName);

                if ($queueName !== '' && ! in_array($queueName, $queues, true)) {
                    $queues[] = $queueName;
                }
            }
        }

        return $queues;
    }

    /** @param array<string, mixed> $processes */
    private function connectionFromProcesses(array $processes): ?string
    {
        $descriptor = array_key_first($processes);

        if (! is_string($descriptor) || ! Str::contains($descriptor, ':')) {
            return null;
        }

        return Str::before($descriptor, ':');
    }

    /** @param array<string, mixed> $processes */
    private function processCount(array $processes): int
    {
        $count = 0;

        foreach ($processes as $processCount) {
            if (is_numeric($processCount)) {
                $count += (int) $processCount;
            }
        }

        return $count;
    }

    /** @return array<int, SupervisorWarningData> */
    private function warnings(?int $timeout, ?int $retryAfter): array
    {
        if ($timeout === null || $retryAfter === null || $timeout < $retryAfter) {
            return [];
        }

        return [new SupervisorWarningData(
            title: 'Unsafe timeout configuration',
            description: "The {$timeout}-second worker timeout must remain shorter than the {$retryAfter}-second retry-after value to prevent overlapping attempts.",
        )];
    }

    private function retryAfter(string $connection): ?int
    {
        $value = $this->config->get("queue.connections.{$connection}.retry_after");

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $options */
    private function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $options */
    private function intOption(array $options, string $key): ?int
    {
        $value = $options[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return int|array<int, int>|null
     */
    private function backoffOption(array $options): int|array|null
    {
        $value = $options['backoff'] ?? null;

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value) || $value === []) {
            return null;
        }

        $backoff = [];

        foreach ($value as $seconds) {
            if (! is_numeric($seconds)) {
                return null;
            }

            $backoff[] = (int) $seconds;
        }

        return $backoff;
    }

    /** @param array<string, mixed> $options */
    private function boolOption(array $options, string $key): ?bool
    {
        $value = $options[$key] ?? null;

        return is_bool($value) ? $value : null;
    }
}
