<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Jobs\ReconcilePaymentJob;
use App\Jobs\ImportFaspayJob;
use App\Jobs\ExportPGToJournalsJob;
use App\Jobs\UpdateTransactionsTableJob;

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
        $schedule->job((new ReconcilePaymentJob()))->cron('* 1 * * *');
        $schedule->job((new UpdateTransactionsTableJob()))->cron('40 1 * * *');
        $schedule->job((new ImportFaspayJob()))->cron('30 2 * * *');
        $schedule->job((new ExportPGToJournalsJob()))->cron('30 3 * * *');
    }    
}
