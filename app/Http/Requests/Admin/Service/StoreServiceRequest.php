<?php

namespace App\Http\Requests\Admin\Service;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'integer',
                'exists:service_categories,id',
            ],

            'name' => [
                'required',
                'string',
                'max:180',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:200',
                'unique:services,slug',
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'thumbnail' => [
                'nullable',
                'string',
                'max:255',
            ],

            'base_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'default_duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:10080',
            ],

            'status' => [
                'required',
                'in:active,inactive',
            ],

            'is_featured' => [
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }
}