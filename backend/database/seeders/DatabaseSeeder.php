<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, StoreSetupSeeder::class]);

        if (app()->environment(['local', 'development'])) {
            $this->call([DemoSeeder::class, CatalogSeeder::class, DemoContentSeeder::class, DemoOrdersSeeder::class]);
        }
    }
}
