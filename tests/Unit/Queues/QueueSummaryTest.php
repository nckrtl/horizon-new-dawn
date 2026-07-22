<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\QueueManager;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\TagRepository;
use NckRtl\HorizonNewDawn\Batches\BatchesData;
use NckRtl\HorizonNewDawn\Batches\BatchJobsData;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobRetryEligibility;
use NckRtl\HorizonNewDawn\FailedJobs\FailedJobsData;
use NckRtl\HorizonNewDawn\Jobs\JobsData;
use NckRtl\HorizonNewDawn\Queues\Data\QueuePauseTargetData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueRowData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueWaitThresholdData;
use NckRtl\HorizonNewDawn\Queues\Data\QueueWaitThresholdTargetData;
use NckRtl\HorizonNewDawn\Queues\QueueBatchesData;
use NckRtl\HorizonNewDawn\Queues\QueueJobsData;
use NckRtl\HorizonNewDawn\Queues\QueuePauseMetadata;
use NckRtl\HorizonNewDawn\Queues\QueuePauseStatus;
use NckRtl\HorizonNewDawn\Queues\QueueSummary;
use NckRtl\HorizonNewDawn\Queues\QueueWaitThresholdStatus;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardThrows;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonBatch;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

function queueSummaryCoordinator(
    JobRepository $jobRepository,
    BatchRepository $batchRepository,
    MetricsRepository $metrics,
): QueueSummary {
    $jobs = new JobsData($jobRepository);
    $batches = new BatchesData(
        $batchRepository,
        new BatchJobsData($jobRepository, $jobs),
        app(ConnectionResolverInterface::class),
    );

    return new QueueSummary(
        new QueueJobsData(
            $jobRepository,
            $jobs,
            new FailedJobsData(
                $jobRepository,
                mockDashboardContract(TagRepository::class),
                $jobs,
                new FailedJobRetryEligibility,
            ),
            app(CacheFactory::class),
        ),
        new QueueBatchesData($batchRepository, $batches, app(CacheFactory::class)),
        $metrics,
    );
}

function queueSummaryRow(): QueueRowData
{
    $pauseStatus = new QueuePauseStatus(
        app(QueueManager::class),
        new QueuePauseMetadata(app(CacheFactory::class)),
    );
    $redis = $pauseStatus->for('redis', 'reports');
    $sqs = $pauseStatus->for('sqs', 'reports');

    return new QueueRowData(
        name: 'reports',
        connections: ['redis', 'sqs'],
        pauseTargets: [
            new QueuePauseTargetData(
                connection: 'redis',
                paused: $redis->paused,
                pausedUntil: $redis->pausedUntil,
                ready: 3,
                reserved: 1,
                delayed: 0,
                total: 4,
            ),
            new QueuePauseTargetData(
                connection: 'sqs',
                paused: $sqs->paused,
                pausedUntil: $sqs->pausedUntil,
                ready: 4,
                reserved: 1,
                delayed: 3,
                total: 8,
            ),
        ],
        ready: 7,
        reserved: 2,
        delayed: 3,
        processes: 5,
        wait: 12,
        waitThreshold: new QueueWaitThresholdData(
            status: QueueWaitThresholdStatus::Exceeded,
            decisiveConnection: 'redis',
            waitSeconds: 8,
            thresholdSeconds: 5,
            oldestReadyAgeSeconds: 300,
            oldestReadyConnection: 'redis',
            targets: [
                new QueueWaitThresholdTargetData(
                    connection: 'redis',
                    status: QueueWaitThresholdStatus::Exceeded,
                    monitored: true,
                    waitSeconds: 8,
                    thresholdSeconds: 5,
                    oldestReadyAgeSeconds: 300,
                ),
                new QueueWaitThresholdTargetData(
                    connection: 'sqs',
                    status: QueueWaitThresholdStatus::Exceeded,
                    monitored: true,
                    waitSeconds: 12,
                    thresholdSeconds: 10,
                    oldestReadyAgeSeconds: 120,
                ),
            ],
        ),
    );
}

beforeEach(function (): void {
    app(CacheFactory::class)->store()->clear();
    config()->set('horizon-new-dawn.poll_interval', 0);

    if (queuePausingIsSupported()) {
        app(QueueManager::class)->resume('redis', 'reports');
        app(QueueManager::class)->resume('sqs', 'reports');
    }
});

it('combines live queue retained history batches and snapshot metrics', function (): void {
    requireQueuePausing();

    $deadline = CarbonImmutable::now()->addHour();
    $metadata = new QueuePauseMetadata(app(CacheFactory::class));
    $metadata->storeUntil('sqs', 'reports', $deadline);
    app(QueueManager::class)->pauseFor('sqs', 'reports', $deadline);

    $jobs = mockDashboardContract(JobRepository::class);
    $pending = horizonJob(0, 'pending-1');
    $pending->queue = 'reports';
    dashboardReturnsFor($jobs, 'countPending', [], 1);
    dashboardReturnsFor($jobs, 'getPending', ['-1'], collect([$pending]));
    $completed = horizonJob(0, 'completed-1');
    $completed->queue = 'reports';
    dashboardReturnsFor($jobs, 'countCompleted', [], 1);
    dashboardReturnsFor($jobs, 'getCompleted', ['-1'], collect([$completed]));
    $failed = horizonJob(0, 'failed-1');
    $failed->queue = 'reports';
    dashboardReturnsFor($jobs, 'countFailed', [], 1);
    dashboardReturnsFor($jobs, 'getFailed', ['-1'], collect([$failed]));
    $silenced = horizonJob(0, 'silenced-1');
    $silenced->queue = 'reports';
    dashboardReturnsFor($jobs, 'countSilenced', [], 1);
    dashboardReturnsFor($jobs, 'getSilenced', ['-1'], collect([$silenced]));

    $batch = horizonBatch('batch-1', pendingJobs: 4);
    $batch->options['queue'] = 'reports';
    $batchRepository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batchRepository, 'get', [50, null], [$batch]);

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturnsFor($metrics, 'throughputForQueue', ['reports'], 4);
    dashboardReturnsFor($metrics, 'runtimeForQueue', ['reports'], 2500.0);

    $summary = queueSummaryCoordinator($jobs, $batchRepository, $metrics)
        ->forQueue(queueSummaryRow());

    expect($summary->toArray())->toMatchArray([
        'available' => true,
        'name' => 'reports',
        'connections' => ['redis', 'sqs'],
        'pauseTargets' => [
            [
                'connection' => 'redis',
                'paused' => false,
                'pausedUntil' => null,
                'ready' => 3,
                'reserved' => 1,
                'delayed' => 0,
                'total' => 4,
            ],
            [
                'connection' => 'sqs',
                'paused' => true,
                'pausedUntil' => $deadline->timestamp,
                'ready' => 4,
                'reserved' => 1,
                'delayed' => 3,
                'total' => 8,
            ],
        ],
        'pendingJobs' => 1,
        'pendingComplete' => true,
        'pendingReserved' => 2,
        'pendingReadyNow' => 7,
        'pendingDelayed' => 3,
        'failedJobs' => 1,
        'failedComplete' => true,
        'failedJobsPerMinuteComplete' => true,
        'failedJobsPastHourComplete' => true,
        'failedJobsPastDayComplete' => true,
        'failedRetentionMinutes' => 10080,
        'completedJobs' => 1,
        'completedComplete' => true,
        'completedJobsPerMinuteComplete' => true,
        'completedJobsPastHourComplete' => true,
        'completedJobsPastDayComplete' => true,
        'completedRetentionMinutes' => 60,
        'silencedJobs' => 1,
        'silencedComplete' => true,
        'batches' => 1,
        'activeBatches' => 1,
        'batchesComplete' => true,
        'processes' => 5,
        'waitThreshold' => [
            'status' => 'exceeded',
            'decisiveConnection' => 'redis',
            'waitSeconds' => 8,
            'thresholdSeconds' => 5,
            'oldestReadyAgeSeconds' => 300,
            'oldestReadyConnection' => 'redis',
            'targets' => [
                [
                    'connection' => 'redis',
                    'status' => 'exceeded',
                    'monitored' => true,
                    'waitSeconds' => 8,
                    'thresholdSeconds' => 5,
                    'oldestReadyAgeSeconds' => 300,
                ],
                [
                    'connection' => 'sqs',
                    'status' => 'exceeded',
                    'monitored' => true,
                    'waitSeconds' => 12,
                    'thresholdSeconds' => 10,
                    'oldestReadyAgeSeconds' => 120,
                ],
            ],
        ],
        'throughput' => 4,
        'averageRuntime' => 2.5,
        'message' => null,
    ]);
});

it('keeps queue data available when snapshot metrics fail', function (): void {
    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'countPending', [], 0);
    dashboardReturnsFor($jobs, 'countCompleted', [], 0);
    dashboardReturnsFor($jobs, 'countFailed', [], 0);
    dashboardReturnsFor($jobs, 'countSilenced', [], 0);
    $batchRepository = mockDashboardContract(BatchRepository::class);
    dashboardReturnsFor($batchRepository, 'get', [50, null], []);
    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardThrows($metrics, 'throughputForQueue', new RuntimeException('metrics secret'));

    $summary = queueSummaryCoordinator($jobs, $batchRepository, $metrics)
        ->forQueue(queueSummaryRow());

    expect($summary->available)->toBeTrue()
        ->and($summary->processes)->toBe(5)
        ->and($summary->throughput)->toBeNull()
        ->and($summary->averageRuntime)->toBeNull()
        ->and($summary->message)->toBeNull();
});

it('preserves partial retained-data warnings in the composed queue summary', function (): void {
    $jobs = mockDashboardContract(JobRepository::class);
    dashboardReturnsFor($jobs, 'countPending', [], 1);
    dashboardThrows($jobs, 'getPending', new RuntimeException('pending secret'));
    dashboardReturnsFor($jobs, 'countCompleted', [], 0);
    dashboardReturnsFor($jobs, 'countFailed', [], 0);
    dashboardReturnsFor($jobs, 'countSilenced', [], 0);

    $batchRepository = mockDashboardContract(BatchRepository::class);
    dashboardThrows($batchRepository, 'get', new RuntimeException('batch secret'));

    $metrics = mockDashboardContract(MetricsRepository::class);
    dashboardReturnsFor($metrics, 'throughputForQueue', ['reports'], 0);

    $summary = queueSummaryCoordinator($jobs, $batchRepository, $metrics)
        ->forQueue(queueSummaryRow());

    expect($summary->available)->toBeTrue()
        ->and($summary->pendingComplete)->toBeFalse()
        ->and($summary->batchesComplete)->toBeFalse()
        ->and($summary->message)->toBe(
            'Some retained job data is currently unavailable. Retained batches are currently unavailable.',
        );
});

it('creates an explicit unavailable summary without inventing zero values', function (): void {
    $summary = QueueSummary::unavailable('reports', 'Horizon queues are currently unavailable.');

    expect($summary->available)->toBeFalse()
        ->and($summary->name)->toBe('reports')
        ->and($summary->pendingJobs)->toBeNull()
        ->and($summary->silencedJobs)->toBeNull()
        ->and($summary->processes)->toBeNull()
        ->and($summary->waitThreshold)->toBeNull()
        ->and($summary->throughput)->toBeNull()
        ->and($summary->averageRuntime)->toBeNull()
        ->and($summary->message)->toBe('Horizon queues are currently unavailable.');
});
