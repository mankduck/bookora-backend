<?php

namespace App\Http\Requests\Admin\ServiceCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('serviceCategory');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:service_categories,id',
                Rule::notIn([$category?->id]),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',

                Rule::unique(
                    'service_categories',
                    'slug'
                )->ignore($category?->id),
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

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'status' => [
                'required',
                'in:active,inactive',
            ],
        ];
    }
}