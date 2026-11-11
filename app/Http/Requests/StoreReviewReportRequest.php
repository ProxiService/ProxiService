<?php

namespace App\Http\Requests;

use App\Models\ServiceReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('serviceReview') instanceof ServiceReview && $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in([
                'spam',
                'abusive',
                'false_information',
                'conflict_of_interest',
                'other',
            ])],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
