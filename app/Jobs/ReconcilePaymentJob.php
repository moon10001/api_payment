<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateTransactionsTableJob;


class ReconcilePaymentJob extends Job
{
    public function __construct() {}
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

        $transactions = DB::table('mt940')
        ->select(
          'mt940.payment_date',
          'prm_payments.name',
          'prm_payments.id',
          'mt940.va',
          DB::raw(
            'SUM(tr_payment_details.nominal) as nominal'
          )
        )
        ->join('tr_invoices', 'tr_invoices.temps_id', 'mt940.temps_id')
        ->join('tr_payment_details', 'tr_invoices.id', 'tr_payment_details.invoices_id')
        ->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        ->whereRaw('DATE(mt940.created_at) = ?', $date)
        ->groupBy('mt940.va', 'mt940.payment_date', 'prm_payments.name', 'prm_payments.id')
        ->orderBy('mt940.payment_date', 'ASC')
        ->get();

        foreach($unitsVA as $va) {
          if (empty($va->va_code)) {
            continue;
          }

          $filteredTransactions = $transactions->filter(function($transaction) use ($va) {
            return str_starts_with($transaction->va, $va->va_code);
          });

          if ($filteredTransactions->isNotEmpty()) {
            foreach($filteredTransactions as $transaction) {
              DB::table('daily_reconciled_reports')->updateOrInsert([
                'units_id' => $va->unit_id,
                'payment_date' => $transaction->payment_date,
                'prm_payments_id' => $transaction->id,
              ],[
                'units_id' => $va->unit_id,
                'payment_date' => $transaction->payment_date,
                'prm_payments_id' => $transaction->id,
                'nominal' => $transaction->nominal,
              ]);
            }
          }
        }
      } catch (Exception $e) {
        throw $e;
      }
    }
}
