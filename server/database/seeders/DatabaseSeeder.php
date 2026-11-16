<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Artisan::call('permission:sync');

        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            ArticleCategorySeeder::class,
            ArticleSeeder::class,
            AnnouncementSeeder::class,
            MonitorLogSeeder::class,
        ]);
    }
}
