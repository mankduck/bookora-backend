<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class CheckCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:100',
            ],

            'variant_id' => [
                'required',
                'integer',
                'exists:service_variants,id',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(
                    trim((string) $this->input('code'))
                ),
            ]);
        }
    }
}