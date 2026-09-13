<?php

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique(
                    'users',
                    'email'
                ),
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique(
                    'users',
                    'phone'
                ),
            ],

            'password' => [
                'required',
                'string',
                Password::min(8),
            ],

            'status' => [
                'required',

                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Vui lòng nhập tên khách hàng.',

            'name.max' =>
                'Tên khách hàng không được vượt quá 150 ký tự.',

            'email.email' =>
                'Email không đúng định dạng.',

            'email.unique' =>
                'Email này đã được sử dụng.',

            'phone.required' =>
                'Vui lòng nhập số điện thoại.',

            'phone.unique' =>
                'Số điện thoại này đã được sử dụng.',

            'password.required' =>
                'Vui lòng nhập mật khẩu.',

            'password.min' =>
                'Mật khẩu phải có ít nhất 8 ký tự.',

            'status.required' =>
                'Vui lòng chọn trạng thái tài khoản.',

            'status.in' =>
                'Trạng thái tài khoản không hợp lệ.',
        ];
    }
}