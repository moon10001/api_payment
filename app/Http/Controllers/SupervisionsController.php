<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SupervisionsController extends Controller
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
      $coa = $request->coa;
      if (isset($request->year)) {
        $year = $request->year;
      }
      $prmPayments = $this->getPrmPayments();
      $paymentId = $prmPayments->where('coa', '=', $coa)->pluck('id')->first();

      $q = DB::table('tr_invoices')
      ->selectRaw('
        periode_year,
        periode_month,
        SUM(tr_payment_details.nominal) as total_invoice
      ')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->where('tr_invoices.periode_year', $year)
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->groupBy('periode_month')
      ->get();

      $details = DB::table('tr_invoices')
      ->selectRaw('
        SUM(tr_payment_details.nominal) as total_nominal,
        tr_payment_details.payments_id,
        tr_payment_details.invoices_id,
        tr_invoices.periode_month,
        tr_invoice_details.payments_type
      ')
      ->join('tr_invoice_details', 'tr_invoice_details.invoices_id', 'tr_invoices.id')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->where('tr_invoices.periode_year', $year)
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va[0]['va_code'].'%')
      ->where('tr_invoice_details.payments_type', '=', 'H2H')
      ->groupBy('periode_month', 'payments_type')
      ->get();

      return [
        'raw' => $q,
        'details' => $details
      ];

	  $res = [];
	  $summary=[
	  	'total' => 0,
	  	'outstanding' => 0,
	  	'h2h' => 0,
	  	'pg' => 0,
	  	'offline' => 0,
	  	'totalPayment' => 0,
	  ];

    foreach($q as $o) {
    	$month = $o->periode_month;
      if (!isset($res[$month])) {
        $res[$month] = [
          'total' => 0,
          'outstanding' => 0,
          'h2h' => 0,
          'pg' => 0,
          'offline' => 0,
          'totalPayment' => 0,
        ];
      }
   		$res[$month]['total'] += $o->total_invoice;
   		$summary['total'] += $o->total_invoice;
      $res[$month]['h2h'] += $o->total_h2h;
      $summary['h2h'] += $o->total_h2h;
      $res[$month]['pg'] += $o->total_pg;
      $summary['pg'] += $o->total_pg;
      $res[$month]['offline'] += $o->total_offline
      $summary['offline'] += $o->total_offline
      $res[$month]['totalPayment'] = $o->total_h2h + $o->total_pg + $o->total_offline;
      $summary['totalPayment'] += $o->total_h2h + $o->total_pg + $o->total_offline;
      $res[$month]['outstanding'] += $o->total_invoice - $o->total_payment;
  	  $summary['outstanding'] = $summary['total'] - $summary['totalPayment'];
    }

      return [
      	'report' => $res,
      	'summary' => $summary,
      ];
    }

    public function options(Request $request) {
      $coa = Cache::get('prm_payments', function() {
        $res = $this->getPrmPayments();
        $data = [];
        foreach($res as $prm) {
          array_push($data, [
            'label' => $prm->name,
            'value' => $prm->coa
          ]);
        }
        return $data;
      });
      return $coa;
    }
}
