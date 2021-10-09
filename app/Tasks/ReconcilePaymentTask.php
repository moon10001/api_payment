<?php

namespace App\Tasks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReconcilePaymentTask {

  public function reconcile() {
    try {
      $date = date("Y-m-d");
      $unitsVA = Cache::get('prm_va', function() {
        $res = DB::table('prm_va')->get();
        return $res;
      });

      foreach($unitsVA as $va) {
        DB::enableQueryLog();
        if (empty($va->va_code)) {
          continue;
        }
        $transactions = DB::table('mt940')
        ->select(
          'mt940.payment_date',
          'prm_payments.name',
          'prm_payments.id',
          DB::raw(
            'SUM(tr_payment_details.nominal) as nominal'
          )
        )
        ->join('tr_invoices', 'tr_invoices.temps_id', 'mt940.temps_id')
        ->join('tr_payment_details', 'tr_invoices.id', 'tr_payment_details.invoices_id')
        ->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        ->where('mt940.payment_date', $date)
        ->where('mt940.va', 'LIKE', $va->va_code.'%')
        ->groupBy('mt940.payment_date', 'prm_payments.name', 'prm_payments.id')
        ->orderBy('mt940.payment_date', 'ASC')
        ->get();

        if ($transactions->isNotEmpty()) {
          foreach($transactions as $transaction) {
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
