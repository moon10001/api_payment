<?php

namespace Database\Seeders;

use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Database\Seeder;
use App\Jobs\ImportFaspayJob;
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
        $begin = new DateTime('2023-02-02');
        $end = new DateTime('2023-07-07');

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);
		
//		$updateStatementDateJob = new ImportFaspayJob();
//		$updateStatementDateJob->handle();

        foreach ($period as $dt) {
          $job = new ExportPGToJournalsJob($dt->format('Y-m-d'));
          $job->handle();
        }
    }
}
