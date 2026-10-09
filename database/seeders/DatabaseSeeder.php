<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a development database with the Northstar Manufacturing demo.
     * Needs DEMO_PASSWORD and refuses to run in production.
     */
    public function run(): void
    {
        $this->call(NorthstarDemoSeeder::class);
    }
}
