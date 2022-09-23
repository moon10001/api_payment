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
	      
//	    foreach($unitsVA as $va) {
        	$transactions = DB::table('tr_invoices')
        	->select(
          		'tr_invoices.payments_date',
          		'prm_payments.id',
          		'prm_va.unit_id',
          		DB::raw(
            		'SUM(tr_payment_details.nominal) as nominal'
          		),
          		DB::raw('NOW()'),
          		DB::raw('NOW()'),
          		'prm_payments.coa'
        	)
	    	->join('tr_payment_details', function($join) {
          		$join->on('tr_payment_details.invoices_id', '=','tr_invoices.id');
       		})
       		->join('prm_va', function($join) {
       			$join->on('prm_va.va_code', '=', DB::raw('SUBSTR(tr_invoices.temps_id, 1, 3)'));
       		})
        	->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        	->whereRaw('DATE_FORMAT(tr_invoices.payments_date, "%Y-%m-%d") = ?', $this->date)
        	->whereNull('tr_invoices.faspay_id')
        	->groupBy('prm_va.unit_id', 'tr_invoices.payments_date', 'prm_payments.id', 'prm_payments.coa')
        	->orderBy('tr_invoices.payments_date', 'ASC');

	        DB::table('daily_reconciled_reports')->insertUsing([
    	    	'payment_date', 'prm_payments_id', 'units_id', 'nominal', 'created_at', 'updated_at', 'coa'
        	], $transactions);
        
  //     }
        /*
        foreach($unitsVA as $va) {
          if (empty($va->va_code)) {
            continue;
          }
          $filteredTransactions = $transactions->filter(function($transaction) use ($va) {
            return str_starts_with($transaction->va, $va->va_code);
          });

          if ($filteredTransactions->isNotEmpty()) {
            foreach($filteredTransactions as $transaction) {
              DB::table('daily_reconciled_reports')->insert([
                'units_id' => $va->unit_id,
                'payment_date' => $transaction->payment_date,
                'prm_payments_id' => $transaction->id,
                'coa' => $transaction->coa,
                'nominal' => $transaction->nominal,
                'updated_at' => Carbon::now(),
              ]);
            }
          }
          var_dump($va->va_code, $filteredTransactions);
        }*/
      } catch (Exception $e) {
        throw $e;
      }
    }
}
