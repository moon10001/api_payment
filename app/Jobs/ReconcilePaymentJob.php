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
        DB::enableQueryLog();

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
        ->where('mt940.payment_date', '<=', $date)
        ->groupBy('mt940.va', 'mt940.payment_date', 'prm_payments.name', 'prm_payments.id')
        ->orderBy('mt940.payment_date', 'ASC')
        ->get();

        var_dump(DB::getQueryLog());

        foreach($unitsVA as $va) {
          if (empty($va->va_code)) {
            continue;
          }

          $filteredTransactions = $transactions::all()->filter(function($transaction) use ($va) {
            return starts_with($transactions->va, $va->code);
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
        dispatch(new UpdateTransactionsTableJob($date));
      } catch (Exception $e) {
        throw $e;
      }
    }
}
