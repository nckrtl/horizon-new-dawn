<?php

declare(strict_types=1);

namespace NckRtl\HorizonNewDawn\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RetryFailedJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
