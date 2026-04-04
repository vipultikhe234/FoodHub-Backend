<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Merchant;
use App\Models\City;

echo "Merchants in DB:\n";
foreach (Merchant::with('city')->get() as $m) {
    echo "Merchant: {$m->name} - City ID: {$m->city_id} - City Name: " . ($m->city->name ?? 'None') . "\n";
}
