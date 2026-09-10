<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'target_contacted' => ['required','integer','min:0','max:10000'],
            'target_meetings' => ['required','integer','min:0','max:10000'],
            'target_proposals' => ['required','integer','min:0','max:10000'],
            'target_deals' => ['required','integer','min:0','max:10000'],
            'target_revenue' => ['required','numeric','min:0','max:9999999999999'],
        ];
    }
}
