<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateTransactionsTableJob;
use Carbon\Carbon;

class ReconcilePaymentJob extends Job
{
    protected $invoicesIds = [];

    public function __construct($invoicesIds) {
      $this->invoicesIds = $invoicesIds;
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      try {
        $date = date("Y-m-d");
        $unitsVA = Cache::get('prm_va', function() {
          $res = DB::table('prm_va')->get();
          return $res;
        });
        
	    DB::enableQueryLog();
        $transactions = DB::table('mt940')
        ->select(
          'mt940.payment_date',
          'prm_payments.id',
          'prm_va.unit_id',
          DB::raw(
            'SUM(tr_payment_details.nominal) as nominal'
          ),
          DB::raw('NOW()'),
          DB::raw('NOW()')
        )
        ->join('prm_va', function($join) {
          $join->on('mt940.va', 'like', DB::raw('CONCAT(prm_va.va_code, "%")')); 
        })
	    ->join('tr_payment_details', function($join) {
          $join->on('tr_payment_details.invoices_id', '=', DB::raw('CONCAT("INV-", mt940.temps_id, DATE_FORMAT(STR_TO_DATE(mt940.periode_from, "%m%y"), "%y%m"))'));
       	})
        ->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        ->whereRaw('DATE(mt940.created_at) = ?', $date)
        ->groupBy('prm_va.va_code', 'mt940.payment_date', 'prm_payments.id')
        ->orderBy('mt940.payment_date', 'ASC');
        
        DB::table('daily_reconciled_reports')->insertUsing([
        	'payment_date', 'prm_payments_id', 'units_id', 'nominal', 'created_at', 'updated_at'
        ], $transactions);
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
