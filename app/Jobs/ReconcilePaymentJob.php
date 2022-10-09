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
        $unitsVA =  DB::table('prm_va')->get();

        DB::enableQueryLog();
	      
       	$transactions = DB::connection('mysql')->table('mt940')
       	->select(
       		'tr_invoices.payments_date as payment_date',
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
      	->join('prm_va', 'prm_va.va_code', 'mt940.va_code')
       	->where('mt940.payment_date', $this->date)
       	->groupBy('units_id', 'mt940.payment_date', 'prm_payments.id', 'prm_payments.coa')
       	->orderBy('mt940.payment_date', 'ASC')->get();
       	
		$arr = $transactions->map(function($o) { return (array) $o; })->toArray();

      	DB::connection('report_db')->table('daily_reconciled_reports')->insert($arr);

      	var_dump(DB::getQueryLog());
      } catch (Exception $e) {
        throw $e;
      }
    }
}
