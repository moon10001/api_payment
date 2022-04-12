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
          'mt940.va',
          'prm_payments.id',
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
        ->groupBy('mt940.payment_date', 'mt940.va', 'prm_payments.id')
        ->orderBy('mt940.payment_date', 'ASC')
        ->orderBy('mt940.va', 'ASC')
        ->orderBy('tr_payment_details.periode', 'ASC')
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
