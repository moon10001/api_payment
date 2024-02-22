<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateTransactionsTableJob;
use Carbon\Carbon;

class ReconcilePaymentJob extends Job
{
    protected $date;

    public function __construct($date = '') {
      if ($date != '') {
        $this->date = $date;
      } else {
	       $this->date = date('Y-m-d', strtotime("-1 days"));
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
        $unitsVA =  DB::table('prm_va')->get();

        DB::enableQueryLog();
	      
       	$transactions = DB::connection('mysql')->table('mt940')
       	->select(
       		'mt940.payment_date',
       		'prm_payments.id as prm_payments_id',
       		DB::raw(
       			'SUBSTR(mt940.temps_id, 1, 3) as units_id'
       		),
       		DB::raw(
          		'SUM(tr_payment_details.nominal) as nominal'
       		),
	        'prm_payments.coa as coa'
      	)
      	->join('tr_invoices', 'tr_invoices.mt940_id', 'mt940.id')
    	->join('tr_payment_details', function($join) {
       		$join->on('tr_payment_details.invoices_id', '=','tr_invoices.id');
   		})
      	->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
       	->where('mt940.payment_date', $this->date)
       	->groupBy('units_id', 'mt940.payment_date', 'prm_payments.id', 'prm_payments.coa')
       	->orderBy('mt940.payment_date', 'ASC')->get();


		$discounts = DB::table('tr_payment_discounts')
		->select(DB::raw('SUM(tr_payment_discounts.nominal) as discount, payments_id'))
		->join('tr_invoices', 'tr_invoices.id', 'tr_payment_discounts.invoices_id')
		->join('mt940', 'mt940.id', 'tr_invoices.mt940_id')
		->where('mt940.payment_date', $this->date)
		->groupBy('payments_id')
		->get();
				       	
		var_dump($discounts);
       	$fixedTransactions = $transactions->map(function ($item, $key) use ($unitsVA, $discounts) {
       		$discount = $discounts->first(function($value, $key) use($item) {
       			return $value->payments_id == $item->prm_payments_id;
       		});
       		
       		$va = $unitsVA->first(function ($value, $key) use ($item)  {
       			return $value->va_code == $item->units_id;
       		});

       		if ($discount != null) {
       			echo ('DISCOUNT '. $item->nominal . ' - ' . $discount->discount);
	       		$item->nominal = $item->nominal - $discount->discount;
       		}
       		$item->units_id = $va->unit_id;
       		return $item;
       	});
       	
		$arr = $fixedTransactions->map(function($o) { return (array) $o; })->toArray();

      	DB::connection('report_db')->table('daily_reconciled_reports')->insert($arr);

      	var_dump(DB::getQueryLog());
      } catch (Exception $e) {
        throw $e;
      }
    }
}
