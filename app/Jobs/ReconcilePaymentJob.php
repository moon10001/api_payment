<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateTransactionsTableJob;
use Carbon\Carbon;

class ReconcilePaymentJob extends Job
{
    protected $invoicesIds = [];
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
        //$date = date("Y-m-d");
        $unitsVA = Cache::get('prm_va', function() {
          $res = DB::table('prm_va')->where('va_code', '<>', '""')->get();
          return $res;
        });

	    DB::enableQueryLog();

	    $mt = DB::table('mt940')
	    ->where('payment_date', $this->date)
	    ->get();

	    foreach($unitsVA as $va) {
          if (empty($va->va_code)) {
             continue;
          }
          $filteredTransactions = $mt->filter(function($transaction) use ($va) {
            return str_starts_with($transaction->va, $va->va_code);
          });
          if ($filteredTransactions->isNotEmpty()) {
          	foreach($filteredTransactions as $item) {
	    		    $paymentDetails = DB::table('tr_payment_details')
	    		    ->select(
    	    			'prm_payments.id',
    	    			'tr_payment_details.nominal',
    	    			'prm_payments.coa',
    	    			'prm_payments.name'
    	    		)
    	    		->join('prm_payments', 'prm_payments.id', 'tr_payment_details.payments_id')
    	    		->join('tr_invoices' , 'tr_payment_details.invoices_id', '=', 'tr_invoices.id')
    	    		->where('temps_id', $item->temps_id)
    	    		->whereRaw(
                'IF(CHAR_LENGTH(tr_invoices.periode) = 4, tr_invoices.periode_date BETWEEN ? and ?, tr_invoices.periode = ?)',
                $item->periode_date_from, $item->periode_date_to, $item->periode_from
              )
              ->whereRaw('DATE(tr_invoices.payments_date) = ?', $item->payment_date)
    	    		->get();
  	       		foreach($paymentDetails as $transaction) {
  	              DB::table('daily_reconciled_reports')->insert([
  	                'units_id' => $va->unit_id,
  	                'payment_date' => $item->payment_date,
  	                'prm_payments_id' => $transaction->id,
  	                'nominal' => $transaction->nominal,
  	                'updated_at' => Carbon::now(),
  	                'coa' => $transaction->coa,
  	                'description' => $transaction->name
  	              ]);
  	          }
  	            //var_dump(DB::getQueryLog());
  	        }
	    	 }
	     }
        //$transactions = DB::table('mt940')
        //->select(
        //  'mt940.created_at',
        //  'mt940.va',
        //  'prm_payments.id',
        //  'prm_payments.coa',
        //  'prm_payments.name',
        //  DB::raw(
        //    'SUM(tr_payment_details.nominal) as nominal'
        //  ),
        //  DB::raw('NOW()'),
        //  DB::raw('NOW()')
        //)
        //->join('tr_invoices', function($join) {
        //  $join->on('tr_invoices.temps_id','=','mt940.temps_id');
        //  $join->on('tr_invoices.periode_date', '>=', 'mt940.periode_date_from');
        //  $join->on('tr_invoices.periode_date', '<=', 'mt940.periode_date_to');
        //})
        //->join('tr_payment_details', 'tr_invoices.id', 'tr_payment_details.invoices_id')
        //->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        //->whereRaw('DATE(mt940.created_at) = ?', $this->date)
        //->groupBy('mt940.created_at', 'mt940.va', 'prm_payments.id')
        //->orderBy('mt940.created_at', 'ASC')
        //->orderBy('mt940.va', 'ASC')
        //->orderBy('tr_payment_details.periode', 'ASC')
        //->get();

        //foreach($unitsVA as $va) {
        //  if (empty($va->va_code)) {
        //    continue;
        //  }
        //  $filteredTransactions = $transactions->filter(function($transaction) use ($va) {
        //    return str_starts_with($transaction->va, $va->va_code);
        //  });
        //  if ($filteredTransactions->isNotEmpty()) {
        //    foreach($filteredTransactions as $transaction) {
        //      DB::table('daily_reconciled_reports')->insert([
        //        'units_id' => $va->unit_id,
        //        'payment_date' => $transaction->created_at,
        //        'prm_payments_id' => $transaction->id,
        //        'nominal' => $transaction->nominal,
        //        'updated_at' => Carbon::now(),
        //        'coa' => $transaction->coa,
        //        'description' => $transaction->name
        //      ]);
        //    }
        //    //var_dump(DB::getQueryLog());
        //  }
        //}
      } catch (Exception $e) {
        throw $e;
      }
    }
}
