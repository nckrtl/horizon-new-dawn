<?php

declare(strict_types=1);

use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PrunableBatchRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Horizon\Horizon;

use function NckRtl\HorizonNewDawn\Tests\Support\dashboardReturns;
use function NckRtl\HorizonNewDawn\Tests\Support\mockDashboardContract;
use function Pest\Laravel\delete;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function (): void {
    withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    Horizon::auth(static fn (): bool => true);
});

afterEach(function (): void {
    Horizon::auth(static fn (): bool => true);
});

it('clears finished batches through a custom prunable repository contract', function (): void {
    $repository = mockDashboardContract(PrunableBatchRepository::class);
    dashboardReturns($repository, 'prune', 3);
    app()->instance(BatchRepository::class, $repository);

    delete('/horizon/batches')
        ->assertRedirect()
        ->assertSessionHas('toast.success', 'Cleared 3 finished batches.');
});

it('sets finished at when Laravel cancels a batch and clears it through normal pruning', function (): void {
    $table = 'horizon_new_dawn_batch_pruning';
    Schema::dropIfExists($table);
    Schema::create($table, function (Blueprint $table): void {
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

    $batchRow = static fn (string $id): array => [
        'id' => $id,
        'name' => $id,
        'total_jobs' => 1,
        'pending_jobs' => 1,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => serialize([]),
        'cancelled_at' => null,
        'created_at' => now()->subHour()->getTimestamp(),
        'finished_at' => null,
    ];

    try {
        DB::table($table)->insert([
            $batchRow('normal-cancelled'),
            [...$batchRow('finished'), 'finished_at' => now()->subMinutes(15)->getTimestamp()],
            $batchRow('active'),
        ]);

        $repository = new DatabaseBatchRepository(
            app(BatchFactory::class),
            DB::connection(),
            $table,
        );
        $repository->cancel('normal-cancelled');
        app()->instance(BatchRepository::class, $repository);

        expect(DB::table($table)->where('id', 'normal-cancelled')->value('finished_at'))->not->toBeNull();

        delete('/horizon/batches')
            ->assertRedirect()
            ->assertSessionHas('toast.success', 'Cleared 2 finished batches.');

        expect(DB::table($table)->orderBy('id')->pluck('id')->all())->toBe(['active']);
    } finally {
        Schema::dropIfExists($table);
    }
});

it('reports repositories that cannot prune batches', function (): void {
    app()->instance(BatchRepository::class, mockDashboardContract(BatchRepository::class));

    delete('/horizon/batches')
        ->assertRedirect()
        ->assertSessionHas('toast.error', 'Finished batches could not be cleared.');
});

it('honors Horizon authorization when clearing batches', function (): void {
    Horizon::auth(static fn (): bool => false);

    delete('/horizon/batches')->assertForbidden();
});
