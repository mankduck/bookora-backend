<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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

            'start_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after:now',
            ],

            'customer_name' => [
                'required',
                'string',
                'max:150',
            ],

            'customer_phone' => [
                'required',
                'string',
                'max:20',
            ],

            'customer_email' => [
                'nullable',
                'email',
                'max:190',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'coupon_code' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('coupon_code')) {
            $this->merge([
                'coupon_code' => strtoupper(
                    trim(
                        (string) $this->input('coupon_code')
                    )
                ),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'variant_id.required' =>
                'Vui lòng chọn gói dịch vụ.',

            'variant_id.exists' =>
                'Gói dịch vụ không tồn tại.',

            'start_at.required' =>
                'Vui lòng chọn thời gian.',

            'start_at.date_format' =>
                'Thời gian không đúng định dạng.',

            'start_at.after' =>
                'Không thể đặt lịch trong quá khứ.',

            'customer_name.required' =>
                'Vui lòng nhập họ tên.',

            'customer_phone.required' =>
                'Vui lòng nhập số điện thoại.',

            'customer_email.email' =>
                'Email không đúng định dạng.',
        ];
    }
}