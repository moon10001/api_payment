<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateTransactionsTableJob;
use Carbon\Carbon;

class Mt940ForcedOKJob extends Job
{
    protected $date;

    public function __construct($date = '') {
      if ($date != '') {
        $this->date = date('Y-m-d', strtotime($date));
      } else {
	    $this->date = date('Y-m-d');
      }
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    try {
      	$mt940 = DB::table('mt940')
      	->select('id', 'nominal', 'va')
      	->where('payment_date', $this->date)
      	->get(); 
      	echo("BEGIN ==== ".$this->date."\n");
		$ids = $mt940->pluck('id');
		
		$trInvoices = DB::table('tr_invoices')
		->select(DB::raw('SUM(nominal) as total_inv, mt940_id'))
		->whereIn('mt940_id', $ids)
		->groupBy('mt940_id')
		->get();
		
		$total = 0;
		      	
      	foreach($mt940 as $row) {
      		$nominal = $row->nominal;
      		$filtered = $trInvoices->where('mt940_id', $row->id)->first();     		
      		if (!is_null($filtered)) {
      			$diff = floatval($nominal) - floatval($filtered->total_inv);
      			if ($diff > 0) {
      				$total += $diff;
					echo($row->id."\n");
					echo($row->nominal."\n");
					echo($filtered->total_inv."\n");
					echo($total."\n\n");
				}      			
      		} else {
				$diff = floatval($nominal);
				$total += $diff;
				echo ($row->id."\n");
				echo ($row->nominal."\n");
				echo ($total."\n\n");
      		}
      	}
      	return $total;  
      } catch (Exception $e) {
      	throw $e;
      }
    }
}
