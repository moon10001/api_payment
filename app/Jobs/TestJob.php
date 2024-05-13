<?php

namespace App\Jobs;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TestJob extends Job
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use InteractsWithQueue, Queueable, SerializesModels;


    public function handle() {
    	echo("Running TestJob");
    	//$output = new ConsoleOutput();
    	for($i=0; $i<10;$i++) {
    	//$output->writeln('Test Job '.$i);
    	Log::info('Test Job'.$i);
    	app('log')->channel('slack')->info('Test Job'.$i);
    	
    	}
    }
}
