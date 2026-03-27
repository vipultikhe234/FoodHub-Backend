<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'merchant_id'      => 'required|exists:Merchants,id',
            'idempotency_key'    => 'nullable|string|max:100',
            'address_id'         => 'nullable|exists:user_addresses,id',
            'delivery_address'   => 'required|string|max:500',
            'payment_method'     => 'required|in:cod,stripe,razorpay',
            'order_type'         => 'required|in:delivery,pickup',
            'coupon_code'        => 'nullable|string|exists:coupons,code',
            
            // Financial Breakdown (Atomic Snapshot)
            'delivery_fee'       => 'nullable|numeric|min:0',
            'packing_charge'     => 'nullable|numeric|min:0',
            'platform_fee'       => 'nullable|numeric|min:0',
            'tax_amount'         => 'nullable|numeric|min:0',
            
            // Items
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.mrp_price'  => 'nullable|numeric|min:0',
        ];
    }
}

