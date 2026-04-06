<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'merchant_id' => 'nullable|exists:merchants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'stock' => 'nullable|integer|min:0',
            'is_veg' => 'boolean',
            'spicy_level' => 'nullable|integer|min:0|max:3',
            'calories' => 'nullable|string',
            'preparation_time' => 'nullable|string',
            'is_popular' => 'boolean',
            'is_recommended' => 'boolean',
            'is_new' => 'boolean',
            'tax_rate' => 'nullable|numeric',
            'is_available' => 'boolean',
            'has_variants' => 'boolean',
            'is_active' => 'boolean',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer|exists:product_variants,id',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.quantity' => 'nullable|string|max:255',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
        ];
    }
}

