<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_id' => [
                'required',
                'integer',
                'exists:service_variants,id',
            ],

            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'variant_id.required' =>
                'Vui lòng chọn gói dịch vụ.',

            'variant_id.exists' =>
                'Gói dịch vụ không tồn tại.',

            'date.required' =>
                'Vui lòng chọn ngày.',

            'date.date_format' =>
                'Ngày không đúng định dạng.',

            'date.after_or_equal' =>
                'Không thể đặt lịch trong quá khứ.',
        ];
    }
}