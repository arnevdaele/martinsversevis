<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Safe to run in production, and to run again: it only adds what is missing.
 * Demo content lives in {@see DemoSeeder}; the first admin comes from
 * `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync');

        foreach ([
            CustomerType::BUSINESS => ['name' => 'Zakelijk', 'description' => 'Horeca, handelaars en andere bedrijven.', 'sort_order' => 1],
            CustomerType::PRIVATE => ['name' => 'Particulier', 'description' => 'Klanten die voor zichzelf bestellen.', 'sort_order' => 2],
        ] as $slug => $attributes) {
            CustomerType::firstOrCreate(['slug' => $slug], $attributes + ['is_system' => true]);
        }
    }
}
