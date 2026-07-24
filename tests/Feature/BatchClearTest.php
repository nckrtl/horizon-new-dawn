<?php

declare(strict_types=1);

use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use NckRtl\HorizonNewDawn\Batches\BatchClearScope;
use NckRtl\HorizonNewDawn\Batches\ClearableBatches;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardExpects;
use function NckRtl\HorizonNewDawn\Tests\Support\horizonJob;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);

    Schema::dropIfExists('horizon_new_dawn_batch_clearing');
    Schema::create('horizon_new_dawn_batch_clearing', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->integer('total_jobs');
        $table->integer('pending_jobs');
        $table->integer('failed_jobs');
        $table->text('failed_job_ids');
        $table->mediumText('options')->nullable();
        $table->integer('cancelled_at')->nullable();
        $table->integer('created_at');
        $table->integer('finished_at')->nullable();
    });

    $now = now()->getTimestamp();

    DB::table('horizon_new_dawn_batch_clearing')->insert([
        batchClearRow('complete', totalJobs: 1, pendingJobs: 0, finishedAt: $now),
        batchClearRow('incomplete', failedJobs: 1, failedJobIds: ['failed-1']),
        batchClearRow('cancelled', pendingJobs: 0, cancelledAt: $now, finishedAt: $now),
        batchClearRow('active', totalJobs: 2, pendingJobs: 1),
        batchClearRow('retry-live', failedJobs: 1, failedJobIds: ['failed-2']),
        batchClearRow('cancelled-live', cancelledAt: $now, finishedAt: $now),
        batchClearRow('cancelled-counted-active', cancelledAt: $now, finishedAt: $now),
    ]);

    $repository = new DatabaseBatchRepository(
        app(BatchFactory::class),
        DB::connection(),
        'horizon_new_dawn_batch_clearing',
    );
    $jobs = mockDashboardContract(JobRepository::class);
    dashboardExpects($jobs, 'countPending', times: 'zeroOrMoreTimes', value: 2);
    dashboardExpects($jobs, 'getPending', ['-1'], 'zeroOrMoreTimes', new Collection([
        batchClearPendingJob(0, 'retry-live-job', 'retry-live'),
        batchClearPendingJob(1, 'cancelled-live-job', 'cancelled-live'),
    ]));

    app()->instance(BatchRepository::class, $repository);
    app()->instance(JobRepository::class, $jobs);
});

afterEach(function (): void {
    Schema::dropIfExists('horizon_new_dawn_batch_clearing');
    Horizon::auth(static fn (): bool => true);
});

it('counts only safely clearable batches in each scope', function (): void {
    $counts = app(ClearableBatches::class)->counts();

    expect($counts->complete)->toBe(1)
        ->and($counts->incomplete)->toBe(1)
        ->and($counts->cancelled)->toBe(1)
        ->and($counts->finished)->toBe(2);
});

it('clears the requested batch scope without deleting in-progress batches', function (
    string $scope,
    string $message,
    array $remaining,
): void {
    delete("/horizon/batches/{$scope}")
        ->assertRedirect()
        ->assertSessionHas('toast.success', $message);

    expect(DB::table('horizon_new_dawn_batch_clearing')->orderBy('id')->pluck('id')->all())
        ->toBe($remaining);
})->with([
    'complete' => [
        'complete',
        'Cleared 1 complete batch.',
        [
            'active',
            'cancelled',
            'cancelled-counted-active',
            'cancelled-live',
            'incomplete',
            'retry-live',
        ],
    ],
    'incomplete' => [
        'incomplete',
        'Cleared 1 incomplete batch.',
        [
            'active',
            'cancelled',
            'cancelled-counted-active',
            'cancelled-live',
            'complete',
            'retry-live',
        ],
    ],
    'cancelled' => [
        'cancelled',
        'Cleared 1 cancelled batch.',
        ['active', 'cancelled-counted-active', 'cancelled-live', 'complete', 'incomplete', 'retry-live'],
    ],
    'finished' => [
        'finished',
        'Cleared 2 finished batches.',
        ['active', 'cancelled', 'cancelled-counted-active', 'cancelled-live', 'retry-live'],
    ],
]);

it('rejects unsupported batch clearing scopes', function (): void {
    delete('/horizon/batches/everything')->assertMethodNotAllowed();
});

it('honors Horizon authorization when clearing batches', function (): void {
    Horizon::auth(static fn (): bool => false);

    delete('/horizon/batches/'.BatchClearScope::Finished->value)->assertForbidden();
});

/**
 * @param  array<int, string>  $failedJobIds
 * @return array<string, mixed>
 */
function batchClearRow(
    string $id,
    int $totalJobs = 1,
    int $pendingJobs = 1,
    int $failedJobs = 0,
    array $failedJobIds = [],
    ?int $cancelledAt = null,
    ?int $finishedAt = null,
): array {
    return [
        'id' => $id,
        'name' => $id,
        'total_jobs' => $totalJobs,
        'pending_jobs' => $pendingJobs,
        'failed_jobs' => $failedJobs,
        'failed_job_ids' => json_encode($failedJobIds, JSON_THROW_ON_ERROR),
        'options' => serialize([]),
        'cancelled_at' => $cancelledAt,
        'created_at' => now()->subHour()->getTimestamp(),
        'finished_at' => $finishedAt,
    ];
}

function batchClearPendingJob(int $index, string $id, string $batchId): object
{
    $job = horizonJob($index, $id);
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['data']['batchId'] = $batchId;
    $job->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    $job->status = 'pending';

    return $job;
}
