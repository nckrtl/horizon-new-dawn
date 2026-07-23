<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardSummaryData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardSupervisorsData;
use NckRtl\HorizonNewDawn\Dashboard\Data\DashboardWorkloadData;
use NckRtl\HorizonNewDawn\Dashboard\Data\FailurePreviewData;
use NckRtl\HorizonNewDawn\Dashboard\Data\RecentFailuresData;
use NckRtl\HorizonNewDawn\Dashboard\Data\SupervisorData;
use NckRtl\HorizonNewDawn\Dashboard\Data\SupervisorGroupData;
use NckRtl\HorizonNewDawn\Dashboard\Data\SupervisorScalingData;
use NckRtl\HorizonNewDawn\Dashboard\Data\WorkloadItemData;
use NckRtl\HorizonNewDawn\Dashboard\Data\WorkloadSplitData;
use NckRtl\HorizonNewDawn\Instances\LocalInstanceName;
use NckRtl\HorizonNewDawn\Queues\Data\QueueWaitThresholdData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThreshold;
use Throwable;

final readonly class DashboardData
{
    public function __construct(
        private JobRepository $jobs,
        private MetricsRepository $metrics,
        private SupervisorRepository $supervisors,
        private MasterSupervisorRepository $masterSupervisors,
        private QueueFactory $queues,
        private WaitTimeCalculator $waitTimes,
        private QueuePauseStatus $queuePauseStatus,
        private DashboardPendingState $pendingState,
        private DashboardBatchSummary $batchSummary,
        private RedisFactory $redis,
        private QueueWaitThreshold $waitThreshold,
    ) {}

    public function summary(): DashboardSummaryData
    {
        try {
            $masters = $this->masterSupervisors->all();
            $pausedMasters = 0;

            foreach ($masters as $master) {
                if (is_object($master) && ($master->status ?? null) === 'paused') {
                    $pausedMasters++;
                }
            }

            $status = match (true) {
                $masters === [] => HorizonStatus::Inactive,
                $pausedMasters === count($masters) => HorizonStatus::Paused,
                default => HorizonStatus::Running,
            };

            $processes = 0;

            foreach ($this->supervisors->all() as $supervisor) {
                if (! is_object($supervisor) || ! is_array($supervisor->processes ?? null)) {
                    continue;
                }

                foreach ($supervisor->processes as $processCount) {
                    if (is_int($processCount)) {
                        $processes += $processCount;
                    }
                }
            }

            $waits = [];

            foreach ($this->waitTimes->calculate() as $queue => $wait) {
                if (is_string($queue) && (is_int($wait) || is_float($wait))) {
                    $waits[$queue] = $wait;
                }
            }

            $maxWaitQueue = null;
            $maxWaitSeconds = 0;

            foreach ($waits as $queue => $wait) {
                if ($maxWaitQueue === null || $wait > $maxWaitSeconds) {
                    $maxWaitQueue = $queue;
                    $maxWaitSeconds = $wait;
                }
            }

            $pendingState = $this->pendingState->forQueues($waits);
            $batchSummary = $this->batchSummary->get();
            $failedRetentionMinutes = max(0, (int) config('horizon.trim.failed', 10080));
            $completedRetentionMinutes = max(0, (int) config('horizon.trim.completed', 60));
            $failedJobs = $this->jobsByPeriod('failed_jobs', $failedRetentionMinutes);
            $completedJobs = $this->jobsByPeriod('completed_jobs', $completedRetentionMinutes);
            [$queueWithMaxRuntime, $queueWithMaxThroughput] = $this->queueMetricLeaders();

            return new DashboardSummaryData(
                available: true,
                status: $status,
                failedJobs: $this->jobs->countFailed(),
                completedJobs: $this->jobs->countCompleted(),
                pendingJobs: $this->jobs->countPending(),
                pendingReserved: $pendingState->reserved,
                pendingReadyNow: $pendingState->readyNow,
                pendingDelayed: $pendingState->delayed,
                failedJobsPerMinute: round(
                    $failedJobs['hour'] / max(1, min(60, $failedRetentionMinutes)),
                    2,
                ),
                failedJobsPastHour: $failedJobs['hour'],
                failedJobsPastDay: $failedJobs['day'],
                failedRetentionMinutes: $failedRetentionMinutes,
                completedJobsPerMinute: round(
                    $completedJobs['hour'] / max(1, min(60, $completedRetentionMinutes)),
                    2,
                ),
                completedJobsPastHour: $completedJobs['hour'],
                completedJobsPastDay: $completedJobs['day'],
                completedRetentionMinutes: $completedRetentionMinutes,
                activeBatches: $batchSummary->active,
                batchPreviews: $batchSummary->previews,
                processes: $processes,
                waits: $waits,
                maxWaitQueue: $maxWaitQueue,
                maxWaitSeconds: $maxWaitSeconds,
                queueWithMaxRuntime: $this->normalizeQueueName($queueWithMaxRuntime),
                queueWithMaxThroughput: $this->normalizeQueueName($queueWithMaxThroughput),
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new DashboardSummaryData(
                available: false,
                status: HorizonStatus::Unavailable,
                failedJobs: 0,
                completedJobs: 0,
                pendingJobs: 0,
                pendingReserved: null,
                pendingReadyNow: null,
                pendingDelayed: null,
                failedJobsPerMinute: 0,
                failedJobsPastHour: 0,
                failedJobsPastDay: 0,
                failedRetentionMinutes: max(0, (int) config('horizon.trim.failed', 10080)),
                completedJobsPerMinute: 0,
                completedJobsPastHour: 0,
                completedJobsPastDay: 0,
                completedRetentionMinutes: max(0, (int) config('horizon.trim.completed', 60)),
                activeBatches: 0,
                batchPreviews: [],
                processes: 0,
                waits: [],
                maxWaitQueue: null,
                maxWaitSeconds: 0,
                queueWithMaxRuntime: null,
                queueWithMaxThroughput: null,
                message: 'Horizon data is currently unavailable.',
            );
        }
    }

    /** @return array{hour: int, day: int} */
    private function jobsByPeriod(string $key, int $retentionMinutes): array
    {
        $connection = $this->redis->connection('horizon');

        return [
            'hour' => $this->jobsSince(
                $connection,
                $key,
                CarbonImmutable::now()->subMinutes(min(60, $retentionMinutes)),
            ),
            'day' => $this->jobsSince(
                $connection,
                $key,
                CarbonImmutable::now()->subMinutes(min(1440, $retentionMinutes)),
            ),
        ];
    }

    private function jobsSince(Connection $connection, string $key, CarbonImmutable $cutoff): int
    {
        return (int) $connection->zcount(
            $key,
            '-inf',
            (string) ($cutoff->getTimestamp() * -1),
        );
    }

    public function supervisors(): DashboardSupervisorsData
    {
        try {
            /** @var array<string, array{environment: ?string, pid: ?int, status: string, local: bool, items: array<int, SupervisorData>}> $groups */
            $groups = [];
            /** @var array<string, Queue> $queueConnections */
            $queueConnections = [];
            /** @var array<string, int> $readyJobs */
            $readyJobs = [];
            /** @var array<string, float> $queueRuntimes */
            $queueRuntimes = [];

            foreach ($this->masterSupervisors->all() as $master) {
                $name = is_object($master) ? ($master->name ?? null) : null;

                if (! is_string($name) || $name === '') {
                    continue;
                }

                $groups[$name] = [
                    'environment' => is_string($master->environment ?? null) && $master->environment !== ''
                        ? $master->environment
                        : null,
                    'pid' => is_numeric($master->pid ?? null) ? (int) $master->pid : null,
                    'status' => is_string($master->status ?? null) ? $master->status : 'inactive',
                    'local' => LocalInstanceName::matches($name),
                    'items' => [],
                ];
            }

            foreach ($this->supervisors->all() as $supervisor) {
                if (! is_object($supervisor) || ! is_string($supervisor->name ?? null)) {
                    continue;
                }

                $master = is_string($supervisor->master ?? null)
                    ? $supervisor->master
                    : Str::beforeLast($supervisor->name, ':');
                $master = $master === '' ? 'Horizon' : $master;
                $processes = is_array($supervisor->processes ?? null) ? $supervisor->processes : [];
                $options = is_array($supervisor->options ?? null) ? $supervisor->options : [];
                $connection = is_string($options['connection'] ?? null)
                    ? $options['connection']
                    : $this->connectionFromProcesses($processes);
                $queues = $this->queuesFromProcesses($processes);
                $processCount = 0;

                foreach ($processes as $count) {
                    if (is_numeric($count)) {
                        $processCount += (int) $count;
                    }
                }

                $balance = is_string($options['balance'] ?? null) && $options['balance'] !== ''
                    ? ucfirst($options['balance'])
                    : 'Disabled';
                $status = is_string($supervisor->status ?? null) ? $supervisor->status : 'inactive';
                $groups[$master] ??= [
                    'environment' => null,
                    'pid' => null,
                    'status' => $status,
                    'local' => LocalInstanceName::matches($master),
                    'items' => [],
                ];
                $groups[$master]['items'][] = new SupervisorData(
                    id: $supervisor->name,
                    name: Str::afterLast($supervisor->name, ':'),
                    connection: $connection,
                    queues: $queues,
                    processes: $processCount,
                    balancing: $balance,
                    status: $status,
                    scaling: $this->supervisorScaling(
                        options: $options,
                        processes: $processes,
                        status: $status,
                        queueConnections: $queueConnections,
                        readyJobs: $readyJobs,
                        queueRuntimes: $queueRuntimes,
                    ),
                );
            }

            ksort($groups);
            $normalizedGroups = [];

            foreach ($groups as $name => $group) {
                usort(
                    $group['items'],
                    static fn (SupervisorData $left, SupervisorData $right): int => $left->name <=> $right->name,
                );

                $normalizedGroups[] = new SupervisorGroupData(
                    name: $name,
                    environment: $group['environment'],
                    pid: $group['pid'],
                    status: $group['status'],
                    local: $group['local'],
                    items: $group['items'],
                );
            }

            return new DashboardSupervisorsData(
                available: true,
                groups: $normalizedGroups,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new DashboardSupervisorsData(
                available: false,
                groups: [],
                message: 'Horizon supervisors are currently unavailable.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $processes
     * @param  array<string, Queue>  $queueConnections
     * @param  array<string, int>  $readyJobs
     * @param  array<string, float>  $queueRuntimes
     */
    private function supervisorScaling(
        array $options,
        array $processes,
        string $status,
        array &$queueConnections,
        array &$readyJobs,
        array &$queueRuntimes,
    ): ?SupervisorScalingData {
        if (($options['balance'] ?? null) !== 'auto' || $status !== 'running') {
            return null;
        }

        $connection = $options['connection'] ?? null;
        if (! is_string($connection) || $connection === '') {
            return null;
        }

        $processPools = $this->supervisorProcessPools($processes);

        if ($processPools === []) {
            return null;
        }

        try {
            $queue = $queueConnections[$connection] ??= $this->queues->connection($connection);
            $totalReadyJobs = 0;
            $poolWorkloads = [];

            foreach ($processPools as $pool => $processCount) {
                $poolReadyJobs = 0;
                $poolTimeToClear = 0.0;

                foreach (explode(',', $pool) as $queueName) {
                    $descriptor = $connection.':'.$queueName;
                    $queueReadyJobs = $readyJobs[$descriptor] ??= (int) $queue->readyNow($queueName);
                    $queueRuntime = $queueRuntimes[$queueName] ??= max(
                        0.0,
                        (float) $this->metrics->runtimeForQueue($queueName),
                    );
                    $poolReadyJobs += $queueReadyJobs;
                    $poolTimeToClear += $queueReadyJobs * $queueRuntime;
                }

                $totalReadyJobs += $poolReadyJobs;
                $poolWorkloads[$pool] = [
                    'processes' => $processCount,
                    'readyJobs' => $poolReadyJobs,
                    'timeToClear' => $poolTimeToClear,
                ];
            }
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $strategy = ($options['autoScalingStrategy'] ?? null) === 'size' ? 'size' : 'time';
        $currentProcesses = array_sum($processPools);
        $targetProcesses = $this->projectedSupervisorProcesses(
            options: $options,
            poolWorkloads: $poolWorkloads,
            strategy: $strategy,
        );

        return new SupervisorScalingData(
            readyJobs: $totalReadyJobs,
            state: match (true) {
                $targetProcesses > $currentProcesses => SupervisorScalingState::Up,
                $targetProcesses < $currentProcesses => SupervisorScalingState::Down,
                default => SupervisorScalingState::Steady,
            },
            strategy: $strategy,
            targetProcesses: $targetProcesses,
        );
    }

    /**
     * @param  array<string, mixed>  $processes
     * @return array<string, int>
     */
    private function supervisorProcessPools(array $processes): array
    {
        $processPools = [];

        foreach ($processes as $descriptor => $processCount) {
            if (! is_numeric($processCount)) {
                continue;
            }

            $pool = Str::after($descriptor, ':');

            if ($pool !== '') {
                $processPools[$pool] = max(0, (int) $processCount);
            }
        }

        return $processPools;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, array{processes: int, readyJobs: int, timeToClear: float}>  $poolWorkloads
     */
    private function projectedSupervisorProcesses(
        array $options,
        array $poolWorkloads,
        string $strategy,
    ): int {
        $minimumProcesses = max(0, (int) ($options['minProcesses'] ?? 1));
        $maximumProcesses = max(0, (int) ($options['maxProcesses'] ?? 1));
        $maximumShift = max(0, (int) ($options['balanceMaxShift'] ?? 1));
        $totalReadyJobs = array_sum(array_column($poolWorkloads, 'readyJobs'));
        $totalTimeToClear = array_sum(array_column($poolWorkloads, 'timeToClear'));
        $desiredProcesses = [];

        foreach ($poolWorkloads as $pool => $workload) {
            if ($totalTimeToClear > 0) {
                $ratio = $strategy === 'size'
                    ? $workload['readyJobs'] / max(1, $totalReadyJobs)
                    : $workload['timeToClear'] / $totalTimeToClear;
                $desiredProcesses[$pool] = $ratio * $maximumProcesses;

                continue;
            }

            $desiredProcesses[$pool] = $workload['readyJobs'] > 0
                ? $maximumProcesses
                : $minimumProcesses;
        }

        asort($desiredProcesses);

        $projectedProcesses = array_sum(array_column($poolWorkloads, 'processes'));
        $maximumPoolProcesses = max(
            $minimumProcesses,
            $maximumProcesses - ((count($poolWorkloads) - 1) * $minimumProcesses),
        );

        foreach ($desiredProcesses as $pool => $desiredPoolProcesses) {
            $currentPoolProcesses = $poolWorkloads[$pool]['processes'];
            $desiredPoolProcesses = (int) ceil($desiredPoolProcesses);

            if ($desiredPoolProcesses > $currentPoolProcesses) {
                $upShift = min(
                    max(0, $maximumProcesses - $projectedProcesses),
                    $maximumShift,
                );
                $nextPoolProcesses = min(
                    $currentPoolProcesses + $upShift,
                    $maximumPoolProcesses,
                    $desiredPoolProcesses,
                );
            } elseif ($desiredPoolProcesses < $currentPoolProcesses) {
                $downShift = min(
                    max(0, $projectedProcesses - $minimumProcesses),
                    $maximumShift,
                );
                $nextPoolProcesses = max(
                    $currentPoolProcesses - $downShift,
                    $minimumProcesses,
                    $desiredPoolProcesses,
                );
            } else {
                continue;
            }

            $projectedProcesses += $nextPoolProcesses - $currentPoolProcesses;
        }

        return $projectedProcesses;
    }

    /** @param array<string, mixed> $processes */
    private function connectionFromProcesses(array $processes): string
    {
        $queue = array_key_first($processes);

        return is_string($queue) && str_contains($queue, ':')
            ? Str::before($queue, ':')
            : 'default';
    }

    /**
     * @param  array<string, mixed>  $processes
     * @return array<int, string>
     */
    private function queuesFromProcesses(array $processes): array
    {
        $queues = [];

        foreach (array_keys($processes) as $queue) {
            $queueNames = str_contains($queue, ':') ? Str::after($queue, ':') : $queue;

            foreach (explode(',', $queueNames) as $queueName) {
                $queueName = trim($queueName);

                if ($queueName !== '') {
                    $queues[] = $queueName;
                }
            }
        }

        return array_values(array_unique($queues));
    }

    private function normalizeQueueName(mixed $queue): ?string
    {
        return is_string($queue) && $queue !== '' ? $queue : null;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function queueMetricLeaders(): array
    {
        $connection = $this->redis->connection('horizon');
        $queueWithMaxRuntime = null;
        $queueWithMaxThroughput = null;
        $maxRuntime = null;
        $maxThroughput = null;

        foreach ($this->metrics->measuredQueues() as $queue) {
            if (! is_string($queue) || $queue === '') {
                continue;
            }

            $snapshots = $connection->zrange('snapshot:queue:'.$queue, -1, -1);
            $snapshotJson = is_array($snapshots) ? ($snapshots[0] ?? null) : null;

            if (! is_string($snapshotJson)) {
                continue;
            }

            try {
                $snapshot = json_decode($snapshotJson, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (! is_array($snapshot)) {
                continue;
            }

            $runtime = $snapshot['runtime'] ?? null;

            if (is_numeric($runtime) && ($maxRuntime === null || $runtime > $maxRuntime)) {
                $maxRuntime = (float) $runtime;
                $queueWithMaxRuntime = $queue;
            }

            $throughput = $snapshot['throughput'] ?? null;

            if (is_numeric($throughput) && ($maxThroughput === null || $throughput > $maxThroughput)) {
                $maxThroughput = (float) $throughput;
                $queueWithMaxThroughput = $queue;
            }
        }

        return [$queueWithMaxRuntime, $queueWithMaxThroughput];
    }

    public function workload(): DashboardWorkloadData
    {
        try {
            $items = [];
            $processes = $this->processesByDescriptor();
            $queueConnections = [];

            // One authoritative calculate() snapshot keeps connection:queue keys through
            // row construction. Never reattach connections by index from a second sorted
            // WorkloadRepository snapshot — wait order can change and queue names collide.
            foreach ($this->waitTimes->calculate() as $descriptor => $wait) {
                if (! is_string($descriptor) || (! is_int($wait) && ! is_float($wait))) {
                    continue;
                }

                $parsed = $this->parseWorkloadDescriptor($descriptor);

                if ($parsed === null) {
                    continue;
                }

                [$connection, $queueName] = $parsed;
                $totalProcesses = $processes[$descriptor] ?? 0;
                $queue = $queueConnections[$connection] ??= $this->queues->connection($connection);

                if (str_contains($queueName, ',')) {
                    $length = 0;
                    $cumulativeWait = 0;
                    $cumulativeWaitCalculated = true;
                    $splitQueues = [];
                    $waitThresholdTargets = [];

                    foreach (explode(',', $queueName) as $part) {
                        $part = trim($part);

                        if ($part === '') {
                            continue;
                        }

                        $partLength = (int) $queue->readyNow($part);
                        $length += $partLength;
                        $cumulativeWaitCalculated = $cumulativeWaitCalculated
                            && $this->canCalculateWait($part, $partLength);
                        $cumulativeWait += $this->waitTimes->calculateTimeToClear(
                            $connection,
                            $part,
                            $totalProcesses,
                        );
                        $waitThresholdTarget = $this->waitThreshold->forTarget(
                            connection: $connection,
                            queue: $part,
                            waitSeconds: $cumulativeWaitCalculated ? $cumulativeWait : null,
                            oldestPendingAt: $this->oldestPendingAt($queue, $part),
                        );
                        $waitThresholdTargets[] = $waitThresholdTarget;
                        $splitQueues[] = [
                            'name' => $part,
                            'length' => $partLength,
                            'wait' => $cumulativeWait,
                            'waitThreshold' => $this->waitThreshold->summarize([$waitThresholdTarget]),
                        ];
                    }

                    $enrichedSplits = $this->enrichSplitQueues($connection, $splitQueues);
                    $threshold = $this->waitThreshold->summarize($waitThresholdTargets);
                    $paused = false;
                    $pausedUntil = null;
                } else {
                    $length = (int) $queue->readyNow($queueName);
                    $enrichedSplits = null;
                    $threshold = $this->waitThreshold->summarize([
                        $this->waitThreshold->forTarget(
                            connection: $connection,
                            queue: $queueName,
                            waitSeconds: $this->canCalculateWait($queueName, $length) ? $wait : null,
                            oldestPendingAt: $this->oldestPendingAt($queue, $queueName),
                        ),
                    ]);
                    $pauseState = $this->queuePauseStatus->for($connection, $queueName);
                    $paused = $pauseState->paused;
                    $pausedUntil = $pauseState->pausedUntil;
                }

                $items[] = new WorkloadItemData(
                    name: $queueName,
                    connection: $connection,
                    length: $length,
                    wait: $wait,
                    processes: $totalProcesses,
                    paused: $paused,
                    pausedUntil: $pausedUntil,
                    splitQueues: $enrichedSplits,
                    waitThreshold: $threshold,
                );
            }

            usort(
                $items,
                static fn (WorkloadItemData $left, WorkloadItemData $right): int => $left->name <=> $right->name,
            );

            return new DashboardWorkloadData(
                available: true,
                items: $items,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new DashboardWorkloadData(
                available: false,
                items: [],
                message: 'Horizon workload is currently unavailable.',
            );
        }
    }

    /** @return array{string, string}|null */
    private function parseWorkloadDescriptor(string $descriptor): ?array
    {
        if (! str_contains($descriptor, ':')) {
            return null;
        }

        [$connection, $queue] = explode(':', $descriptor, 2);

        if ($connection === '' || $queue === '') {
            return null;
        }

        return [$connection, $queue];
    }

    /** @return array<string, int> */
    private function processesByDescriptor(): array
    {
        $processes = [];

        foreach ($this->supervisors->all() as $supervisor) {
            if (! is_object($supervisor) || ! is_array($supervisor->processes ?? null)) {
                continue;
            }

            foreach ($supervisor->processes as $descriptor => $count) {
                if (! is_string($descriptor) || ! is_numeric($count)) {
                    continue;
                }

                $processes[$descriptor] = ($processes[$descriptor] ?? 0) + (int) $count;
            }
        }

        return $processes;
    }

    private function canCalculateWait(string $queue, int $readyJobs): bool
    {
        if ($readyJobs === 0) {
            return true;
        }

        try {
            return $this->metrics->runtimeForQueue($queue) > 0;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function oldestPendingAt(Queue $queue, string $queueName): ?int
    {
        try {
            $createdAt = $queue->creationTimeOfOldestPendingJob($queueName);

            return is_numeric($createdAt) ? (int) $createdAt : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  null|array<int, array{name: string, wait: int|float, length: int, waitThreshold: QueueWaitThresholdData}>  $splitQueues
     * @return null|array<int, WorkloadSplitData>
     */
    private function enrichSplitQueues(string $connection, ?array $splitQueues): ?array
    {
        if ($splitQueues === null) {
            return null;
        }

        return array_map(function (array $queue) use ($connection): WorkloadSplitData {
            $pauseState = $this->queuePauseStatus->for($connection, $queue['name']);

            return new WorkloadSplitData(
                name: $queue['name'],
                length: $queue['length'],
                wait: $queue['wait'],
                paused: $pauseState->paused,
                pausedUntil: $pauseState->pausedUntil,
                waitThreshold: $queue['waitThreshold'],
            );
        }, $splitQueues);
    }

    public function recentFailures(): RecentFailuresData
    {
        try {
            $items = [];
            $limit = max(0, (int) config('horizon-new-dawn.recent_failures_limit'));

            foreach ($this->jobs->getFailed()->take($limit) as $job) {
                if (! is_object($job)) {
                    continue;
                }

                $id = $job->id ?? null;
                $name = $job->name ?? null;
                $queue = $job->queue ?? null;
                $failedAt = $job->failed_at ?? null;

                if (! is_string($id) || ! is_string($name) || ! is_string($queue) || ! is_numeric($failedAt)) {
                    continue;
                }

                $items[] = new FailurePreviewData(
                    id: $id,
                    name: $name,
                    queue: $queue,
                    failedAt: (float) $failedAt,
                );
            }

            return new RecentFailuresData(
                available: true,
                items: $items,
                message: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new RecentFailuresData(
                available: false,
                items: [],
                message: 'Recent Horizon failures are currently unavailable.',
            );
        }
    }
}
