<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Inventory;
use App\Models\Coupon;
use App\Models\MerchantOtherCharge;
use App\Models\UserAddress;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. IDENTITY & ACCESS (Users)
        $admin = User::updateOrCreate(
            ['email' => 'admin@foodhub.com'],
            [
                'name' => 'FoodHub Administrator',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone' => '0000000000',
                'address' => 'HQ, Pune'
            ]
        );

        $merchantUser = User::updateOrCreate(
            ['email' => 'merchant@foodhub.com'],
            [
                'name' => 'FoodHub Merchant',
                'password' => Hash::make('password123'),
                'role' => 'merchant',
                'phone' => '1234567890',
                'address' => 'Shop 01, Pune'
            ]
        );

        $customer = User::updateOrCreate(
            ['email' => 'user@apnacart.com'],
            [
                'name' => 'Jane Customer',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'phone' => '9876543210'
            ]
        );

        $rider = User::updateOrCreate(
            ['email' => 'rider@apnacart.com'],
            [
                'name' => 'Swift Courier',
                'password' => Hash::make('password123'),
                'role' => 'rider',
                'phone' => '1122334455',
                'current_latitude' => 18.5204,
                'current_longitude' => 73.8567
            ]
        );

        // 2. MERCHANT NODES
        $m1 = Merchant::updateOrCreate(
            ['user_id' => $merchantUser->id],
            [
                'name' => 'The Grand Bazaar',
                'description' => 'Premium multi-cuisine and grocery experience.',
                'address' => '123 Gourmet St, Pune',
                'is_open' => true,
                'is_active' => true,
                'image' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                'opening_time' => '09:00:00',
                'closing_time' => '23:00:00',
                'rating' => 4.5
            ]
        );

        $m2 = Merchant::updateOrCreate(
            ['name' => 'Healthy Greens'],
            [
                'user_id' => $admin->id, // Managed by admin for demo
                'description' => 'Fresh salads, nutritious bowls and organic produce.',
                'address' => '456 Wellness Way, Pune',
                'is_open' => true,
                'is_active' => true,
                'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd',
                'opening_time' => '10:00:00',
                'closing_time' => '21:00:00',
                'rating' => 4.8
            ]
        );

        // 3. CATALOG (Categories)
        $pizzaCat = Category::updateOrCreate(
            ['name' => 'Pizza', 'merchant_id' => $m1->id],
            ['image' => 'https://cdn-icons-png.flaticon.com/512/3595/3595455.png', 'status' => true]
        );

        $burgerCat = Category::updateOrCreate(
            ['name' => 'Burgers', 'merchant_id' => $m1->id],
            ['image' => 'https://cdn-icons-png.flaticon.com/512/3075/3075977.png', 'status' => true]
        );

        $drinksCat = Category::updateOrCreate(
            ['name' => 'Beverages', 'merchant_id' => null], // Global category
            ['image' => 'https://cdn-icons-png.flaticon.com/512/2405/2405479.png', 'status' => true]
        );

        // 4. INVENTORY & SKUs (Products with new Advanced Fields)
        // Product 1: Margherita
        $margherita = Product::updateOrCreate(
            ['name' => 'Cheese Margherita Pizza', 'merchant_id' => $m1->id],
            [
                'category_id' => $pizzaCat->id,
                'description' => 'Classic mozzarella and tomato base with gourmet sourdough crust.',
                'price' => 399.00,
                'discount_price' => 349.00,
                'image' => 'https://images.unsplash.com/photo-1574071318508-1cdbad80ad38',
                'is_veg' => true,
                'spicy_level' => 1,
                'calories' => 250,
                'preparation_time' => 15,
                'is_popular' => true,
                'is_recommended' => true,
                'is_new' => false,
                'has_variants' => true,
                'is_available' => true,
                'is_active' => true,
                'stock' => 100
            ]
        );

        $v1 = $margherita->variants()->updateOrCreate(
            ['name' => 'Regular 8"'],
            ['quantity' => '1pc', 'mrp_price' => 399.00, 'price' => 349.00, 'is_active' => true]
        );

        Inventory::updateOrCreate(
            ['product_variant_id' => $v1->id, 'merchant_id' => $m1->id],
            ['stock' => 50, 'reserved_stock' => 0, 'is_available' => true]
        );

        // Product 2: Beef Burger
        $beefBurger = Product::updateOrCreate(
            ['name' => 'Supreme Beef Burger', 'merchant_id' => $m1->id],
            [
                'category_id' => $burgerCat->id,
                'description' => 'Double patty with secret sauce and caramelized onions.',
                'price' => 299.00,
                'image' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd',
                'is_veg' => false,
                'spicy_level' => 2,
                'calories' => 580,
                'preparation_time' => 12,
                'is_popular' => true,
                'is_recommended' => false,
                'is_new' => false,
                'has_variants' => true,
                'is_available' => true,
                'is_active' => true,
                'stock' => 100
            ]
        );

        $v2 = $beefBurger->variants()->updateOrCreate(
            ['name' => 'Combo Pack'],
            ['quantity' => 'Burger + Fries', 'mrp_price' => 450.00, 'price' => 399.00, 'is_active' => true]
        );

        Inventory::updateOrCreate(
            ['product_variant_id' => $v2->id, 'merchant_id' => $m1->id],
            ['stock' => 40, 'reserved_stock' => 0, 'is_available' => true]
        );

        // Product 3: Salad for m2
        $salad = Product::updateOrCreate(
            ['name' => 'Quinoa Power Bowl', 'merchant_id' => $m2->id],
            [
                'category_id' => $burgerCat->id, // Re-use for demo
                'description' => 'Superfood mix with lemon vinaigrette and fresh avocado.',
                'price' => 450.00,
                'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c',
                'is_veg' => true,
                'spicy_level' => 0,
                'calories' => 320,
                'preparation_time' => 10,
                'is_popular' => true,
                'is_recommended' => true,
                'is_new' => true,
                'has_variants' => true,
                'is_available' => true,
                'is_active' => true,
                'stock' => 50
            ]
        );

        $v3 = $salad->variants()->updateOrCreate(
            ['name' => 'Single Serving'],
            ['quantity' => '300g', 'mrp_price' => 450.00, 'price' => 450.00, 'is_active' => true]
        );

        Inventory::updateOrCreate(
            ['product_variant_id' => $v3->id, 'merchant_id' => $m2->id],
            ['stock' => 20, 'reserved_stock' => 0, 'is_available' => true]
        );

        // 5. PROMOTIONS (Coupons)
        Coupon::updateOrCreate(
            ['code' => 'MARCH26'],
            [
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 2000,
                'expires_at' => Carbon::create(2026, 4, 1),
                'is_active' => true,
                'merchant_id' => $m1->id
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'WELCOME50'],
            [
                'type' => 'fixed',
                'value' => 50,
                'min_order_amount' => 500,
                'expires_at' => Carbon::now()->addMonths(6),
                'is_active' => true,
                'merchant_id' => null
            ]
        );

        // 6. LOGISTICS (Addresses)
        UserAddress::updateOrCreate(
            ['user_id' => $customer->id, 'address_line' => 'Flat 402, Sunshine Apts'],
            [
                'landmark' => 'Near Phoenix Mall',
                'city' => 'Pune',
                'is_default' => true
            ]
        );

        // 7. FINANCIAL OPS (Merchant Charges)
        foreach ([$m1, $m2] as $m) {
            MerchantOtherCharge::updateOrCreate(
                ['merchant_id' => $m->id],
                [
                    'delivery_charge' => 25.00,
                    'packaging_charge' => 15.00,
                    'platform_fee' => 10.00,
                    'delivery_charge_tax' => 5.0,
                    'packaging_charge_tax' => 18.0,
                    'platform_fee_tax' => 18.0
                ]
            );
        }
    }
}
