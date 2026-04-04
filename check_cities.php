<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\City;
use App\Models\Merchant;
use App\Models\Product;

echo "Cities List:\n";
foreach (City::all() as $c) {
    echo "ID: {$c->id} - Name: {$c->name}\n";
    $mCount = Merchant::where('city_id', $c->id)->count();
    $pCount = Product::whereHas('merchant', function($q) use ($c) {
        $q->where('city_id', $c->id);
    })->count();
    echo "  Merchants: {$mCount}, Products: {$pCount}\n";
}
