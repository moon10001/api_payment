<?php

namespace Database\Seeders;

use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Database\Seeder;
use App\Jobs\ReconcilePaymentJob;
use App\Jobs\UpdateTransactionsTableJob;
use App\Jobs\Mt940ForcedOKJob;

class SeedH2H extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $begin = new DateTime('2023-01-01');
        $end = new DateTime('2023-09-31');

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);
//		$jb = new ReconcilePaymentJob('2023-01-05');
//		$jb->handle();
//		$jb = new UpdateTransactionsTableJob('', '', '', '2023-01-05');
//		$jb->handle();
        foreach ($period as $dt) {
//	      $job = new ReconcilePaymentJob($dt->format('Y-m-d'));
//	      $job->handle();
		  $job = new Mt940ForcedOKJob($dt->format('Y-m-d'));
		  $job->handle();
//          $job = new UpdateTransactionsTableJob('','','',$dt->format('Y-m-d'));
//          $job->handle();
        }
    }
}
