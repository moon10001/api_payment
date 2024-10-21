
<?php

namespace Database\Seeders;

use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Database\Seeder;
use App\Jobs\ReconcilePaymentJob;
use App\Jobs\UpdateTransactionsTableJob;

class SeedOverpaid extends Seeder
{
    /**
         * Run the database seeds.
         *
         * @return void
    */
    public function run()
    {
     	$begin = new DateTime('2023-01-01');
        $end = new DateTime('2023-02-01');
                                     	        
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);
		$jb->handle();
        foreach ($period as $dt) {
	        $job = new UpdateTransactionsTableJob('','','',$dt->format('Y-m-d'));
            $job->handle();
        }
    }
 }
                                     	                                                                
                                     	                                                                
                                     	                                                                
                                     	                                                                
                                     	                                                                
