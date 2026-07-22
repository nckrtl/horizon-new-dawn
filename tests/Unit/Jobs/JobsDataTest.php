<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Laravel\Horizon\Contracts\JobRepository;
use NckRtl\HorizonNewDawn\Jobs\JobListType;
use NckRtl\HorizonNewDawn\Jobs\JobsData;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturnsFor;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;

describe('JobsData', function (): void {
    afterEach(function (): void {
        Date::setTestNow();
    });

    it('normalizes a full first page and exposes its next Horizon index', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $jobs = new Collection(array_map(
            static fn (int $index): object => horizonJob($index, "job-{$index}"),
            range(0, 49),
        ));
        dashboardReturns($repository, 'getCompleted', $jobs);
        dashboardReturns($repository, 'countCompleted', 75);

        $page = (new JobsData($repository))->page(JobListType::Completed, -1);
        $first = $page->items[0]->toArray();

        expect($page->current)->toBe(-1)
            ->and($page->next)->toBe(49)
            ->and($page->total)->toBe(75)
            ->and($page->items)->toHaveCount(50)
            ->and($first)->toMatchArray([
                'id' => 'job-0',
                'name' => 'App\\Jobs\\ImportFeed',
                'shortName' => 'ImportFeed',
                'runtime' => 1.5,
                'tags' => ['tenant:1', 'import'],
            ])
            ->and($first)->not->toHaveKeys(['payload', 'exception', 'context']);
    });

    it('uses the failure timestamp for failed job timing', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'failed';
        $job->reserved_at = '1784281001.25';
        $job->completed_at = '1784281002.75';
        $job->failed_at = '1784281004.5';

        $row = (new JobsData($repository))->row($job);

        expect($row)->not->toBeNull()
            ->and($row?->completedAt)->toBeNull()
            ->and($row?->failedAt)->toBe(1_784_281_004.5)
            ->and($row?->runtime)->toBe(3.25);
    });

    it('measures reserved job runtime through the current observation time', function (): void {
        Date::setTestNow(Date::createFromFormat('U.u', '1784281004.500000'));
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'reserved';
        $job->reserved_at = '1784281001.25';

        $row = (new JobsData($repository))->row($job);

        expect($row)->not->toBeNull()
            ->and($row?->completedAt)->toBeNull()
            ->and($row?->failedAt)->toBeNull()
            ->and($row?->runtime)->toBe(3.25);
    });

    it('stops scrolling when Horizon returns a short page', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getPending', new Collection(array_map(
            static fn (int $index): object => horizonJob($index, "pending-{$index}"),
            range(0, 48),
        )));
        dashboardReturns($repository, 'countPending', 49);

        $page = (new JobsData($repository))->page(JobListType::Pending, -1);

        expect($page->next)->toBeNull()->and($page->items)->toHaveCount(49);
    });

    it('uses the silenced repository boundary', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getSilenced', new Collection([horizonJob(0)]));
        dashboardReturns($repository, 'countSilenced', 1);

        $page = (new JobsData($repository))->page(JobListType::Silenced, -1);

        expect($page->items)->toHaveCount(1)->and($page->total)->toBe(1);
    });

    it('returns a safe detail payload without raw serialized commands', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getJobs', new Collection([horizonJob(0)]));

        $detail = (new JobsData($repository))->find('job-1');

        expect($detail)->not->toBeNull()
            ->and($detail?->payload)->toMatchArray([
                'displayName' => 'App\\Jobs\\ImportFeed',
                'data' => ['commandName' => 'App\\Jobs\\ImportFeed'],
            ])
            ->and(json_encode($detail?->payload))->not->toContain('serialized-secret-command')
            ->and(json_encode($detail?->toArray()))->not->toContain('sensitive trace', 'secret":"context');
    });

    it('normalizes batch delayed-until and decoded command diagnostics', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $delayedUntil = 1_784_281_600;
        $command = (object) [
            'customerId' => 42,
            'delay' => (new DateTimeImmutable("@{$delayedUntil}"))->setTimezone(new DateTimeZone('Europe/Amsterdam')),
        ];
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000,
            'tags' => ['tenant:1'],
            'data' => [
                'commandName' => 'App\\Jobs\\ImportFeed',
                'batchId' => 'batch-42',
                'command' => serialize($command),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $detail = (new JobsData($repository))->find('job-1');
        $data = $detail?->payload['data'] ?? null;

        expect($detail)->not->toBeNull()
            ->and($detail?->batchId)->toBe('batch-42')
            ->and($detail?->delayedUntil)->toBe((float) $delayedUntil)
            ->and($data)->toBeArray()
            ->and($data['decodedCommand']['customerId'] ?? null)->toBe(42)
            ->and($data['decodedCommand']['delay']['class'] ?? null)->toBe('DateTimeImmutable')
            ->and(json_encode($detail?->payload))->not->toContain(serialize($command));
    });

    it('bases released job availability on the current Horizon update time', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'pending';
        $job->delay = 60;
        $job->updated_at = '1784281400.5';
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000,
            'data' => [
                'command' => serialize((object) ['delay' => 600]),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $detail = (new JobsData($repository))->find('job-1');

        expect($detail?->delay)->toBe(60)
            ->and($detail?->delayedUntil)->toBe(1_784_281_460.5);
    });

    it('does not expose stale release availability after a job leaves the pending state', function (string $status): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = $status;
        $job->delay = 60;
        $job->updated_at = '1784281500.5';
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000,
            'data' => [
                'command' => serialize((object) ['delay' => 600]),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $detail = (new JobsData($repository))->find('job-1');

        expect($detail?->delay)->toBe(60)
            ->and($detail?->delayedUntil)->toBeNull();
    })->with(['reserved', 'completed']);

    it('does not expose availability for a pending job without a positive release delay', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'pending';
        $job->delay = 0;
        $job->updated_at = '1784281500.5';
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000,
            'data' => [
                'command' => serialize((object) ['delay' => 600]),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $detail = (new JobsData($repository))->find('job-1');

        expect($detail?->delay)->toBe(0)
            ->and($detail?->delayedUntil)->toBeNull();
    });

    it('reads the release timestamp from Horizon when its job projection omits it', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'pending';
        $job->delay = 90;
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000,
            'data' => [
                'command' => serialize((object) ['delay' => 600]),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $connection = mockDashboardContract(Connection::class);
        dashboardReturnsFor($connection, 'hget', ['job-1', 'updated_at'], '1784281500.25');
        $redis = mockDashboardContract(RedisFactory::class);
        dashboardReturnsFor($redis, 'connection', ['horizon'], $connection);
        app()->instance(JobRepository::class, $repository);
        app()->instance(RedisFactory::class, $redis);

        $detail = app(JobsData::class)->find('job-1');

        expect($detail?->delayedUntil)->toBe(1_784_281_590.25);
    });

    it('does not reuse the original command delay when a release timestamp is unavailable', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'pending';
        $job->delay = 60;
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000,
            'data' => [
                'command' => serialize((object) ['delay' => 600]),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $detail = (new JobsData($repository))->find('job-1');

        expect($detail?->delay)->toBe(60)
            ->and($detail?->delayedUntil)->toBeNull();
    });

    it('keeps an initial numeric command delay relative to its pushed time', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $job = horizonJob(0);
        $job->status = 'pending';
        $job->payload = json_encode([
            'displayName' => 'App\\Jobs\\ImportFeed',
            'pushedAt' => 1_784_281_000.25,
            'data' => [
                'command' => serialize((object) ['delay' => 120]),
            ],
        ], JSON_THROW_ON_ERROR);
        dashboardReturns($repository, 'getJobs', new Collection([$job]));

        $detail = (new JobsData($repository))->find('job-1');

        expect($detail?->delay)->toBe(120)
            ->and($detail?->delayedUntil)->toBe(1_784_281_120.25);
    });

    it('extracts a safe batch id from retained Horizon payloads', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        $data = new JobsData($repository);
        $batched = horizonJob(0, 'batched');
        $batched->payload = json_encode(['data' => ['batchId' => 'batch-42']], JSON_THROW_ON_ERROR);
        $unbatched = horizonJob(1, 'unbatched');
        $unbatched->payload = json_encode(['data' => []], JSON_THROW_ON_ERROR);
        $invalid = horizonJob(2, 'invalid');
        $invalid->payload = '{';

        expect($data->batchId($batched))->toBe('batch-42')
            ->and($data->batchId($unbatched))->toBeNull()
            ->and($data->batchId($invalid))->toBeNull();
    });

    it('returns null for a missing job', function (): void {
        $repository = mockDashboardContract(JobRepository::class);
        dashboardReturns($repository, 'getJobs', new Collection);

        expect((new JobsData($repository))->find('missing'))->toBeNull();
    });
});
