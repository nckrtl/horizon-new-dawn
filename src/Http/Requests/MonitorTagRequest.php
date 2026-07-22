<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MonitorTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tag = $this->input('tag');

        if (is_string($tag)) {
            $this->merge(['tag' => trim($tag)]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'tag' => ['required', 'string', 'max:255'],
        ];
    }
}
