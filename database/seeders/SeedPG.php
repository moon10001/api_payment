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
        $begin = new DateTime('2023-01-01');
        $end = new DateTime('2023-10-31');

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
