<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
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
                'max:190',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'avatar' => [
                'nullable',
                'string',
                'max:255',
            ],

            'employee_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'position' => [
                'nullable',
                'string',
                'max:150',
            ],

            'bio' => [
                'nullable',
                'string',
            ],

            'experience_years' => [
                'nullable',
                'integer',
                'min:0',
                'max:80',
            ],

            'is_bookable' => [
                'boolean',
            ],

            'status' => [
                'required',
                'in:active,inactive',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'service_ids' => [
                'nullable',
                'array',
            ],

            'service_ids.*' => [
                'integer',
                'exists:services,id',
            ],

            'schedules' => [
                'nullable',
                'array',
            ],

            'schedules.*.day_of_week' => [
                'required',
                'integer',
                'between:0,6',
            ],

            'schedules.*.start_time' => [
                'required',
                'date_format:H:i',
            ],

            'schedules.*.end_time' => [
                'required',
                'date_format:H:i',
                'after:schedules.*.start_time',
            ],

            'schedules.*.is_working' => [
                'boolean',
            ],
        ];
    }
}