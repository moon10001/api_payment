<?php

namespace Database\Seeders;

use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Database\Seeder;
use App\Jobs\ExportPGToJournalsJob;

class SeedPG extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $begin = new DateTime('2023-04-29');
        $end = new DateTime('2023-05-16');

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
          $job = new ExportPGToJournalsJob($dt->format('Y-m-d'));
          $job->handle();
        }
    }
}
