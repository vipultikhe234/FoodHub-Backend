<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth;

class ProductsImport implements ToModel, WithHeadingRow
{
    protected $merchantId;

    public function __construct($merchantId = null)
    {
        $this->merchantId = $merchantId;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Product([
            'merchant_id'  => $this->merchantId ?? Auth::user()->merchant?->id,
            'category_id'  => $row['category_id'],
            'name'         => $row['name'],
            'description'  => $row['description'] ?? null,
            'price'        => $row['price'],
            'stock'        => $row['stock'] ?? 0,
            'is_available' => true,
            'is_active'    => true,
            'has_variants' => false,
        ]);
    }
}

