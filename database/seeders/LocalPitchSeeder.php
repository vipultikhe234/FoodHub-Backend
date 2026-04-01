<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalPitchSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or Find Country
        $country = DB::table('countries')->where('name', 'India')->first();
        if (!$country) {
            $countryId = DB::table('countries')->insertGetId([
                'name' => 'India',
                'code' => 'IN',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $countryId = $country->id;
        }

        // 2. Create or Find States
        $mh = DB::table('states')->where('name', 'Maharashtra')->where('country_id', $countryId)->first();
        if (!$mh) {
            $mhId = DB::table('states')->insertGetId([
                'country_id' => $countryId,
                'name' => 'Maharashtra',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $mhId = $mh->id;
        }

        $dl = DB::table('states')->where('name', 'Delhi')->where('country_id', $countryId)->first();
        if (!$dl) {
            $dlId = DB::table('states')->insertGetId([
                'country_id' => $countryId,
                'name' => 'Delhi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $dlId = $dl->id;
        }

        // 3. Create Cities
        $cities = ['Mumbai', 'Pune', 'Nagpur'];
        foreach ($cities as $city) {
            DB::table('cities')->updateOrInsert(
                ['state_id' => $mhId, 'name' => $city],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $dlCities = ['New Delhi', 'Gurugram'];
        foreach ($dlCities as $city) {
            DB::table('cities')->updateOrInsert(
                ['state_id' => $dlId, 'name' => $city],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // 4. Update existing merchants to have a city (default to Mumbai for testing)
        $mumbaiId = DB::table('cities')->where('name', 'Mumbai')->first()->id;
        DB::table('merchants')->where('id', '>', 0)->update(['city_id' => $mumbaiId]);
    }
}
