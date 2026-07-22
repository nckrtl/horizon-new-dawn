<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NckRtl\HorizonNewDawn\Batches\BatchCreatedRange;
use NckRtl\HorizonNewDawn\Batches\Data\BatchIndexFiltersData;

final class BatchIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['before_id', 'query', 'queue', 'connection', 'created'] as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $this->merge([$key => trim($value)]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'before_id' => ['nullable', 'string', 'max:255'],
            'query' => ['nullable', 'string', 'max:255'],
            'queue' => ['nullable', 'string', 'max:255'],
            'connection' => ['nullable', 'string', 'max:255'],
            'created' => ['nullable', Rule::enum(BatchCreatedRange::class)],
        ];
    }

    public function beforeId(): ?string
    {
        return $this->nullableString('before_id');
    }

    public function getData(): BatchIndexFiltersData
    {
        $created = $this->nullableString('created');

        return new BatchIndexFiltersData(
            query: $this->nullableString('query'),
            queue: $this->nullableString('queue'),
            connection: $this->nullableString('connection'),
            created: $created === null ? null : BatchCreatedRange::from($created),
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
