<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NckRtl\HorizonNewDawn\Queues\Data\PauseQueueData;

final class PauseQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'connection' => $this->route('connection'),
            'queue' => $this->route('queue'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'connection' => [
                'required',
                'string',
                'max:255',
                Rule::in(array_keys(config('queue.connections', []))),
            ],
            'queue' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ];
    }

    public function getData(): PauseQueueData
    {
        $validated = $this->validated();
        $duration = $validated['duration_minutes'] ?? null;

        return new PauseQueueData(
            connection: $validated['connection'],
            queue: $validated['queue'],
            durationMinutes: $duration === null ? null : (int) $duration,
        );
    }
}
