<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Jobs\ImportMT940Job;
use App\Jobs\ReconcilePaymentJob;
use App\Jobs\ImportFaspayJob;
use App\Jobs\ExportPGToJournalsJob;
use App\Jobs\UpdateTransactionsTableJob;
use App\Jobs\TestJob;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    	$schedule->job((new TestJob))->cron('37 18 * * *');
    	$schedule->job((new ImportMT940Job))->cron('5 14 * * *');
        $schedule->job((new ImportFaspayJob))->cron('30 15 * * *');
    }    
}
