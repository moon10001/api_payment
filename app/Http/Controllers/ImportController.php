<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Console\Output\ConsoleOutput;
use App\Jobs\ImportMT940Job;
use App\Jobs\ReconcilePaymentJob;
use App\Jobs\UpdateTransactionsTableJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ImportController extends BaseController
{

    public function import() {
      $date = date('Y-m-d');
      $this->dispatch((new ImportMT940Job)
      	->chain([
        	new ReconcilePaymentJob($date),
        	new UpdateTransactionsTableJob($date)
      	])->delay(Carbon::now()->addMinutes(1))
      );
      return response()->json([
        'message' => 'Success'
      ]);
    }
}
