<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use NckRtl\HorizonNewDawn\Monitoring\MonitoringTagGuard;

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

    /** @return array<string, array<int, mixed>> */
    public function rules(MonitoringTagGuard $guard): array
    {
        return [
            'tag' => [
                'required',
                'string',
                'max:255',
                static function (string $attribute, mixed $value, Closure $fail) use ($guard): void {
                    if (is_string($value) && ! $guard->isSafe($value)) {
                        $fail('The tag conflicts with Horizon internal storage.');
                    }
                },
            ],
        ];
    }
}
