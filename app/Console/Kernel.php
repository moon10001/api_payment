<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Tasks\ReconcilePaymentTask;

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
        $schedule->call(function() {
          $task = new ReconcilePaymentTask();
          $task->reconcile();
        })->daily();
        $schedule->command('queue:work')->dailyAt('07:40');
    }
}
