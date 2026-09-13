<?php

namespace App\Http\Requests\Admin\Coupon;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Coupon|null $coupon */
        $coupon = $this->route('coupon');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->input('type') === 'percent' && (float) $value > 100) {
                        $fail('Giá trị phần trăm không được vượt quá 100%.');
                    }
                },
            ],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => [
                'nullable',
                'date',
                ...($this->filled('starts_at') ? ['after:starts_at'] : []),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'min_order_amount' => $this->filled('min_order_amount') ? $this->input('min_order_amount') : 0,
            'max_discount_amount' => $this->input('type') === 'fixed'
                ? null
                : ($this->filled('max_discount_amount') ? $this->input('max_discount_amount') : null),
            'usage_limit' => $this->filled('usage_limit') ? $this->input('usage_limit') : null,
            'usage_limit_per_customer' => $this->filled('usage_limit_per_customer') ? $this->input('usage_limit_per_customer') : null,
            'starts_at' => $this->filled('starts_at') ? $this->input('starts_at') : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
        ]);
    }
}
