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
        @total_invoice := SUM(tr_payment_details.nominal) as total_invoice,
        @h2h := IFNULL(SUM(h2h_table.nominal), 0) as total_h2h,
        @pg := IFNULL(SUM(pg_table.nominal), 0) as total_pg,
        @offline := IFNULL(SUM(offline_table.nominal), 0) as total_offline,
        @total_payment := IFNULL(@h2h + @pg + @offline, 0) as total_payment,
        @outstanding := @total_invoice - @total_payment as outstanding
      ')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->leftJoin(DB::Raw('(SELECT
        tr_payment_details.nominal,
        tr_payment_details.payments_id,
        tr_payment_details.invoices_id
        from
        tr_invoice_details
        inner join tr_payment_details on tr_payment_details.invoices_id = tr_invoice_details.invoices_id
        where
        tr_invoice_details.payments_type = "H2H") as h2h_table
        '), function($join) {
          $join->on('h2h_table.invoices_id', '=', 'tr_invoices.invoices_id')
        })
      ->leftJoin(DB::Raw('(SELECT
        tr_payment_details.nominal,
        tr_payment_details.payments_id,
        tr_payment_details.invoices_id
        from
        tr_invoice_details
        inner join tr_payment_details on tr_payment_details.invoices_id = tr_invoice_details.invoices_id
        where
        tr_invoice_details.payments_type = "Faspay") as pg_table
        '), function($join) {
          $join->on('h2h_table.invoices_id', '=', 'tr_invoices.invoices_id')
        })
      ->leftJoin(DB::Raw('(SELECT
        tr_payment_details.nominal,
        tr_payment_details.payments_id,
        tr_payment_details.invoices_id
        from
        tr_invoice_details
        inner join tr_payment_details on tr_payment_details.invoices_id = tr_invoice_details.invoices_id
        where
        tr_invoice_details.payments_type = "Offline") as offline_table
        '), function($join) {
          $join->on('h2h_table.invoices_id', '=', 'tr_invoices.invoices_id')
        })
      ->where('tr_invoices.periode_year', $year)
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->groupBy('periode_month');

      $trInvoices = DB::table('tr_invoices')
      ->select(
        DB::Raw(' tr_invoices.id, MONTH(tr_invoices.periode_date) as month, tr_payment_details.nominal')
      )
      ->join('tr_payment_details', 'tr_payment_details.invoices_id' , '=', 'tr_invoices.id')
      ->whereYear('tr_invoices.periode_date', $year)
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va[0]['va_code'].'%')
      ->get();

      $invoicesId = $trInvoices->pluck('id');
      $trInvoiceDetails = DB::table('tr_invoice_details')
      ->select('tr_invoice_details.invoices_id', 'payments_type', 'tr_payment_details.nominal')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', '=', 'tr_invoice_details.invoices_id')
      ->where('tr_payment_details.payments_id', $paymentId)
      ->whereIn('tr_invoice_details.invoices_id', $invoicesId->unique()->all())
      ->get();
	  $res = [];
	  $summary=[
	  	'total' => 0,
	  	'outstanding' => 0,
	  	'h2h' => 0,
	  	'pg' => 0,
	  	'offline' => 0,
	  	'totalPayment' => 0,
	  ];

      foreach($trInvoices as $o) {
      	$month = $o->month;
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
        $filteredDetails = $trInvoiceDetails->where('invoices_id', $o->id);

 		$res[$month]['total'] += $o->nominal;
 		$summary['total'] += $o->nominal;
        if ($filteredDetails->isNotEmpty()) {
          $res[$month]['h2h'] += $filteredDetails->where('payments_type', '=', 'H2H')->sum('nominal');
          $summary['h2h'] += $filteredDetails->where('payments_type', '=', 'H2H')->sum('nominal');
          $res[$month]['pg'] += $filteredDetails->where('payments_type', '=', 'Faspay')->sum('nominal');
          $summary['pg'] += $filteredDetails->where('payments_type', '=', 'Faspay')->sum('nominal');
          $res[$month]['offline'] += $filteredDetails->where('payments_type', '=', 'Offline')->sum('nominal');
          $summary['offline'] += $filteredDetails->where('payments_type', '=', 'Offline')->sum('nominal');
          $res[$month]['totalPayment'] = $res[$month]['h2h'] + $res[$month]['pg'] + $res[$month]['offline'];
          $summary['totalPayment'] = $summary['h2h'] + $summary['pg'] + $summary['offline'];
          $res[$month]['outstanding'] += $res[$month]['total'] - $res[$month]['totalPayment'];
      	  $summary['outstanding'] = $summary['total'] - $summary['totalPayment'];
        }
      };

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
