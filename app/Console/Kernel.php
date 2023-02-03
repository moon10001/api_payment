<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Tasks\ReconcilePaymentTask;
use App\Jobs\ReconcilePaymentJob;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->exec('echo RUNNING')->everyMinute();
//        $schedule->command('queue:work')->dailyAt('07:40');
        $schedule->job(new ReconcilePaymentJob())->cron('0 1 * * *');
    }
}
