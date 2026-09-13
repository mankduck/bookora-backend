<?php

namespace App\Http\Requests\Admin\ServiceVariant;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceVariantRequest extends FormRequest
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
                'max:180',
            ],

            'code' => [
                'nullable',
                'string',
                'max:100',
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

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'sale_price' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:price',
            ],

            'duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:10080',
            ],

            'deposit_type' => [
                'required',
                'in:none,fixed,percent',
            ],

            'deposit_value' => [
                'nullable',
                'numeric',
                'min:0',
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
        ];
    }
}