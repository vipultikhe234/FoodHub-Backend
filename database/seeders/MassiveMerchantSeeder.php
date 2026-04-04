<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Category;
use App\Models\MerchantCategory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MassiveMerchantSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Identify the Target Area (Read city first)
        $cityId = DB::table('merchants')->whereNotNull('city_id')->value('city_id') ?? 1;
        $city = DB::table('cities')->find($cityId);
        $cityName = $city->name ?? 'Local City';

        $merchantCategories = MerchantCategory::all();

        foreach ($merchantCategories as $mCat) {
            $catName = strtolower($mCat->name);
            
            // Create 5 Merchants for this category
            for ($i = 1; $i <= 5; $i++) {
                $merchantName = $mCat->name . " " . $this->getMerchantSuffix($i);
                
                // Create a User for the Merchant
                $user = User::create([
                    'name' => $merchantName . " Owner",
                    'email' => str_replace(' ', '', strtolower($merchantName)) . $i . "@example.com",
                    'password' => Hash::make('password123'),
                    'role' => 'merchant',
                    'phone' => '9' . str_pad($i . $mCat->id . rand(100, 999), 9, '0', STR_PAD_RIGHT)
                ]);

                // Create the Merchant Node
                $merchant = Merchant::create([
                    'user_id' => $user->id,
                    'merchant_category_id' => $mCat->id,
                    'name' => $merchantName,
                    'description' => "Premium " . $mCat->name . " services in " . $cityName,
                    'address' => "Street " . $i . ", " . $cityName,
                    'city_id' => $cityId,
                    'is_open' => true,
                    'is_active' => true,
                    'rating' => 4.0 + ($i * 0.1),
                    'image' => $this->getTopicImage($catName)
                ]);

                // Create a Product Category for the Merchant
                $pCat = Category::create([
                    'merchant_id' => $merchant->id,
                    'name' => 'Main Menu',
                    'status' => true
                ]);

                // Create 5 Products for this merchant
                $products = $this->getSampleProducts($catName);
                foreach ($products as $pData) {
                    $product = Product::create([
                        'merchant_id' => $merchant->id,
                        'category_id' => $pCat->id,
                        'name' => $pData['name'],
                        'description' => $pData['desc'],
                        'price' => $pData['price'],
                        'image' => $pData['image'],
                        'is_veg' => true,
                        'is_available' => true,
                        'is_active' => true,
                        'stock' => 100
                    ]);

                    // Add a default variant
                    DB::table('product_variants')->insert([
                        'product_id' => $product->id,
                        'name' => 'Standard',
                        'quantity' => '1 unit',
                        'price' => $pData['price'],
                        'mrp_price' => $pData['price'] + 20,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }

    private function getMerchantSuffix($i)
    {
        $suffixes = ['Hub', 'Central', 'Express', 'Elite', 'Direct'];
        return $suffixes[$i - 1] ?? 'Store';
    }

    private function getTopicImage($topic)
    {
        $map = [
            'glossary shop'   => 'https://images.unsplash.com/photo-1542838132-92c53300491e',
            'pharmacy'        => 'https://images.unsplash.com/photo-1471864190281-a93a3070b6de',
            'electronic'      => 'https://images.unsplash.com/photo-1588508065123-bc89bdbf587f',
            'vegetables'      => 'https://images.unsplash.com/photo-1566385101042-1a0aa0c1268c',
            'fruits'          => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b',
            'restaurant'      => 'https://images.unsplash.com/photo-1517248135467-4c7ed9d42339',
            'electrical shop' => 'https://images.unsplash.com/photo-1558237242-70b97950c4aa',
        ];
        return $map[$topic] ?? 'https://images.unsplash.com/photo-1504674900247-0877df9cc836';
    }

    private function getSampleProducts($topic)
    {
        $products = [];
        $data = [
            'glossary shop' => [
                ['name' => 'Premium Basmati Rice', 'price' => 120, 'desc' => 'Long grain fragrant rice.'],
                ['name' => 'Refined Sunflower Oil', 'price' => 180, 'desc' => 'Heart-healthy cooking oil.'],
                ['name' => 'Organic Brown Sugar', 'price' => 90, 'desc' => 'Nutrient-rich alternative to white sugar.'],
                ['name' => 'Assam Black Tea', 'price' => 150, 'desc' => 'Strong and refreshing morning tea.'],
                ['name' => 'Herbal Bath Soap', 'price' => 45, 'desc' => 'Pure neem and aloe vera extracts.'],
            ],
            'pharmacy' => [
                ['name' => 'Paracetamol 500mg', 'price' => 30, 'desc' => 'Effective pain and fever relief.'],
                ['name' => 'Vitamin C Chewables', 'price' => 80, 'desc' => 'Boosts immunity daily.'],
                ['name' => 'Disposable Face Masks', 'price' => 100, 'desc' => 'Box of 50 3-ply protective masks.'],
                ['name' => 'Alcohol Hand Sanitizer', 'price' => 150, 'desc' => 'Kills 99.9% germs on the go.'],
                ['name' => 'Antiseptic Liquid', 'price' => 120, 'desc' => 'Multipurpose hygiene solution.'],
            ],
            'electronic' => [
                ['name' => 'Fast Charging USB-C', 'price' => 299, 'desc' => 'Durable braided cable for quick charge.'],
                ['name' => '10000mAh Power Bank', 'price' => 999, 'desc' => 'Slim design with dual output.'],
                ['name' => 'Wireless Bluetooth Earbuds', 'price' => 1499, 'desc' => 'Crystal clear sound with long battery.'],
                ['name' => '20W PD Wall Charger', 'price' => 499, 'desc' => 'Universal adapter for multiple devices.'],
                ['name' => 'Silent Wireless Mouse', 'price' => 699, 'desc' => 'Ergonomic design for comfy work.'],
            ],
            'vegetables' => [
                ['name' => 'Fresh Red Tomatoes', 'price' => 40, 'desc' => 'Farm-fresh, juicy tomatoes.'],
                ['name' => 'Hill Station Potatoes', 'price' => 35, 'desc' => 'Premium quality potatoes.'],
                ['name' => 'Pink Onions', 'price' => 50, 'desc' => 'Crisp and flavorful onions.'],
                ['name' => 'Fresh Baby Spinach', 'price' => 25, 'desc' => 'Nutrient-dense green leaves.'],
                ['name' => 'Sweet Orange Carrots', 'price' => 60, 'desc' => 'Crunchy and sweet carrots.'],
            ],
            'fruits' => [
                ['name' => 'Royal Gala Apples', 'price' => 180, 'desc' => 'Sweet and crisp imported apples.'],
                ['name' => 'Robusta Bananas', 'price' => 60, 'desc' => 'Potassium-rich healthy fruits.'],
                ['name' => 'Alphonso Mangoes', 'price' => 800, 'desc' => 'The king of fruits (seasonal).'],
                ['name' => 'Nagpur Oranges', 'price' => 90, 'desc' => 'Juicy and tangry citrus delight.'],
                ['name' => 'Green Seedless Grapes', 'price' => 120, 'desc' => 'Fresh and sweet bunch.'],
            ],
            'restaurant' => [
                ['name' => 'Classic Veg Pizza', 'price' => 299, 'desc' => 'Mozzarella, corn, and capsicum.'],
                ['name' => 'Cheesy Grilled Burger', 'price' => 149, 'desc' => 'Crispy patty with molten cheese.'],
                ['name' => 'Penne Arrabiata Pasta', 'price' => 249, 'desc' => 'Spicy tomato sauce with herbs.'],
                ['name' => 'Paneer Tikka Sandwich', 'price' => 129, 'desc' => 'Grilled bread with spicy paneer.'],
                ['name' => 'Garden Fresh Salad', 'price' => 180, 'desc' => 'Mixed seasonal greens with vinaigrette.'],
            ],
            'electrical shop' => [
                ['name' => '9W LED Energy Saver', 'price' => 99, 'desc' => 'Bright white light with low power.'],
                ['name' => '1.5mm Copper Wire', 'price' => 1200, 'desc' => '90m coil for safe home wiring.'],
                ['name' => 'Modular Switch Plate', 'price' => 150, 'desc' => 'Clean design with smooth touch.'],
                ['name' => '5-Pin Power Socket', 'price' => 80, 'desc' => 'Child-safe shutter protection.'],
                ['name' => 'Voltage Tester Pen', 'price' => 45, 'desc' => 'Essential tool for safe electrical work.'],
            ],
        ];

        $topicData = $data[$topic] ?? $data['restaurant'];
        foreach ($topicData as $item) {
            $products[] = [
                'name' => $item['name'],
                'price' => $item['price'],
                'desc' => $item['desc'],
                'image' => $this->getTopicImage($topic) . '?q=80&w=400&auto=format'
            ];
        }
        return $products;
    }
}
