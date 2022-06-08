<?php

namespace Database\Seeders;

use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Database\Seeder;
use App\Jobs\ReconcilePaymentJob;
use App\Jobs\UpdateTransactionsTableJob;

class SeedH2H extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $begin = new DateTime('2021-10-02');
        $end = new DateTime('2021-10-02');

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

$job = new ReconcilePaymentJob('2021-10-02');
$job->handle();
        foreach ($period as $dt) {
//          $job = new ReconcilePaymentJob($dt->format('Y-m-d'));
//          $job->handle();
          //$job = new UpdateTransactionsTableJob('','','',$dt->format('Y-m-d'));
          //$job->handle();
        }
    }
}
