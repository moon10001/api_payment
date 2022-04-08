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
        //$date = date("Y-m-d");
        $date = date('Y-m-d', strtotime('2021-12-01'));
        $unitsVA = Cache::get('prm_va', function() {
          $res = DB::table('prm_va')->where('va_code', '<>', '""')->get();
          return $res;
        });
        DB::enableQueryLog();
        var_dump($date);
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
        ->join('tr_invoices', function($join) {
          $join->on('tr_invoices.temps_id','=','mt940.temps_id');
          $join->on('tr_invoices.periode_date', '>=', 'mt940.periode_date_from');
          $join->on('tr_invoices.periode_date', '<=', 'mt940.periode_date_to');
        })
        ->join('tr_payment_details', 'tr_invoices.id', 'tr_payment_details.invoices_id')
        ->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        ->whereRaw('DATE(mt940.created_at) = ?', $date)
        ->groupBy('mt940.payment_date', 'mt940.va', 'prm_payments.name', 'prm_payments.id')
        ->orderBy('mt940.payment_date', 'ASC')
        ->orderBy('mt940.va', 'ASC')
        ->orderBy('tr_payment_details.periode', 'ASC')
        ->get();
        var_dump($transactions->count());
        //var_dump(DB::getQueryLog());
        foreach($unitsVA as $va) {
          //var_dump($va);
          if (empty($va->va_code)) {
            continue;
          }
          $filteredTransactions = $transactions->filter(function($transaction) use ($va) {
            var_dump($va->va_code, $transaction);
            return str_starts_with($transaction->va, $va->va_code);
          });
          //var_dump($filteredTransactions);
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
            //var_dump(DB::getQueryLog());
          }
          var_dump($va->va_code, $filteredTransactions);
        }*/
      } catch (Exception $e) {
        throw $e;
      }
    }
}
