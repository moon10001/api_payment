<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    private function getPrmPayments() {
      $prmPayments = Cache::get('prm_payments', function() {
        $res = DB::table('prm_payments')->get();
        return $res;
      });
      return $prmPayments;
    }

    public function post(Request $request) {
      $year = date('Y');
      $va = $request->va_code;
      $coa = $request->coa_code;
      if (isset($request->year)) {
        $year = $request->year;
      }
      $prmPayments = $this->getPrmPayments();
      $paymentId = $prmPayments->where('coa', '=', $coa)->pluck('id');

      $trInvoices = DB::table('tr_invoices')
      ->select(
        DB::Raw(' tr_invoices.id, MONTH(tr_invoices.periode_date) as month, tr_payment_details.nominal')
      )
      ->join('tr_payment_details', 'tr_payment_details.invoices_id' , '=', 'tr_invoices.id')
      ->whereYear('tr_invoices.periode_date', $year)
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->get();

      $invoicesId = $trInvoices->pluck('id');
      $trInvoiceDetails = DB::table('tr_invoice_details')
      ->whereIn('invoices_id', $invoicesId->unique()->all())
      ->get();

      $res = [];
      $data = $trInvoices->map(function($o) use($trInvoiceDetails, $res) {
        if (!isset($res[$o->month])) {
          $month = $o->month;
          $res[$month] = [
            'total' => 0,
            'outstanding' => 0,
            'h2h' => 0,
            'pg' => 0,
            'offline' => 0,
          ];
        }
        $filteredDetails = $trInvoiceDetails->where('invoices_id', $o->id);
        if ($filteredDetails->isNotEmpty()) {
          $res[$month]['total'] += $o->nominal;
          $res[$month]['h2h'] += $filteredDetails->where('payments_type', '=', 'H2H')->sum('nominal');
          $res[$month]['pg'] += $filteredDetails->where('payments_type', '=', 'Fastpay')->sum('nominal');
          $res[$month]['offline'] += $filteredDetails->where('payments_type', '=', 'Offline')->sum('nominal');
          $res[$month]['outstanding'] = $res[$month]['total'] - $res[$month]['h2h'] - $res[$month]['pg'] -> $res[$month]['offline'];
        }
      });
      return $res;
    }

    public function options(Request $request) {
      $coa = Cache::get('prm_payments', function() {
        $res = $this->getPrmPayments();
        $data = [];
        foreach($res as $prm) {
          array_push($data, [
            'label' => $data->name,
            'value' => $data->coa
          ]);
        }
        return $data;
      });
      return $coa;
    }
}
