<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\SeedDailyReconciliationReports;
use Database\Seeders\SeedH2H;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call('UsersTableSeeder');
        //$this->call([SeedDailyReconciliationReports::class]);
        $this->call(SeedH2H::class);
        //$this->call(SeedPG::class);
    }
}
