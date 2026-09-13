<?php

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user =
            $this->route('user');

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
                )->ignore($user),
            ],

            'phone' => [
                'required',
                'string',
                'max:30',

                Rule::unique(
                    'users',
                    'phone'
                )->ignore($user),
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
                'Email này đã được sử dụng bởi tài khoản khác.',

            'phone.required' =>
                'Vui lòng nhập số điện thoại.',

            'phone.unique' =>
                'Số điện thoại này đã được sử dụng bởi tài khoản khác.',

            'status.required' =>
                'Vui lòng chọn trạng thái tài khoản.',

            'status.in' =>
                'Trạng thái tài khoản không hợp lệ.',
        ];
    }
}