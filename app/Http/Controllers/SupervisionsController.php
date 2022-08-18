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
      $endYear = $year + 1;

      $va = $request->va_code[0]['va_code'];
      $coa = $request->coa;
      if (isset($request->year)) {
        $year = $request->year;
      }
      $prmPayments = $this->getPrmPayments();
      $paymentId = $prmPayments->where('coa', '=', $coa)->pluck('id')->first();

	    $outstanding = DB::table('tr_invoices')
      ->selectRaw('
        periode_year,
        CONCAT("TA ", periode_year, "/", periode_year+1) as month,
        SUM(tr_payment_details.nominal) as total_invoice
      ')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->where('tr_invoices.periode_date', '>=', ($year-5).'-7-1')
      ->where('tr_invoices.periode_date', '<=', $year.'-6-30')
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->groupBy('periode_year')
      ->get();

      $outstandingDetails = DB::table('tr_invoices')
      ->selectRaw('
        SUM(tr_payment_details.nominal) as total_nominal,
        tr_payment_details.payments_id,
        tr_invoice_details.payments_type
      ')
      ->join('tr_invoice_details', 'tr_invoice_details.invoices_id', 'tr_invoices.id')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->where('tr_invoices.periode_date', '>=', ($year-5).'-7-1')
      ->where('tr_invoices.periode_date', '<=', $year.'-6-30')
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->groupBy('periode_year', 'payments_type')
      ->get();


      $q = DB::table('tr_invoices')
      ->selectRaw('
        periode_year,
        periode_month,
        SUM(tr_payment_details.nominal) as total_invoice
      ')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
	    ->whereBetween('tr_invoices.periode_date', [$year.'-7-1', ($year+1).'-6-30'])
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->groupBy('periode_month')
      ->get();

      $details = DB::table('tr_invoices')
      ->selectRaw('
        SUM(tr_payment_details.nominal) as total_nominal,
        tr_payment_details.payments_id,
        tr_invoices.periode_month,
        tr_invoice_details.payments_type
      ')
      ->join('tr_invoice_details', 'tr_invoice_details.invoices_id', 'tr_invoices.id')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->whereBetween('tr_invoices.periode_date', [$year.'-7-1', ($year+1).'-6-30'])
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.temps_id', 'like', $va.'%')
      ->groupBy('periode_month', 'payments_type')
      ->get();

      $outstandingData = [];
      foreach($outstanding as $o) {
        $month = $o->month;
        $totalPayment = $outstandingDetails->Where('periode_month', $month)->sum('total_nominal');
        $outstandingData[$month] = [
          'total' => $o->total_invoice,
          'outstanding' => $o->total_invoice - $totalPayment,
          'h2h' => $outstandingDetails->where('payments_type', 'H2H')->where('periode_month', $month)->pluck('total_nominal'),
          'pg' => $outstandingDetails->where('payments_type', 'Faspay')->where('periode_month', $month)->pluck('total_nominal'),
          'offline' => $outstandingDetails->where('payments_type', 'Offline')->where('periode_month', $month)->pluck('total_nominal'),
          'totalPayment' => $totalPayment
        ];
      }

  	  $res = collect([]);
      $months = ['7', '8', '9', '10', '11', '12', '1', '2', '3', '4', '5', '6'];
  	  $summary=[
  	  	'total' => $outstanding->sum('total_invoice') + $q->sum('total_invoice'),
  	  	'outstanding' => 0,
  	  	'h2h' => $outstandingDetails->where('payments_type', '=', 'H2H')->sum('total_nominal') + $details->where('payments_type', '=', 'H2H')->sum('total_nominal'),
  	  	'pg' => $outstandingDetails->where('payments_type', '=', 'Faspay')->sum('total_nominal') + $details->where('payments_type', '=', 'Faspay')->sum('total_nominal'),
  	  	'offline' => $outstandingDetails->where('payments_type', '=', 'Offline')->sum('total_nominal') + $details->where('payments_type', '=', 'Offline')->sum('total_nominal'),
  	  	'totalPayment' => $details->sum('total_nominal'),
  	  ];

  	  $summary['outstanding'] = $summary['total'] - $summary['totalPayment'];
      foreach($months as $m) {
        $filtered = $q->where('periode_month', $m)->first();
        $data = $res->where('month', '=', $m)->first();
        
        if (!$data) {
          $data= [
            'month' => $m,
            'total' => $filtered->total_invoice,
            'outstanding' => 0,
            'h2h' => 0,
            'pg' => 0,
            'offline' => 0,
            'totalPayment' => 0,
           ];
           $res->push($data);           
        }
        $data['h2h'] = $details->where('payments_type', 'H2H')->where('periode_month', $m)->pluck('total_nominal');
        $data['pg'] = $details->where('payments_type', 'Faspay')->where('periode_month', $m)->pluck('total_nominal');
        $data['offline'] = $details->where('payments_type', 'Offline')->where('periode_month', $m)->pluck('total_nominal');
        $data['totalPayment'] = $details->where('periode_month', $m)->sum('total_nominal');
        $data['outstanding'] = $data['total'] - $data['totalPayment'];
        
        $res->transform(function($item, $key) use($m, $data) {
        	if ($item['month'] == $m) {
        		$item = $data;
        	}
        	return $item;
        });
        
      }

      return [
        'outstanding' => $outstandingData,
      	'report' => $res->toArray(),
      	'summary' => $summary,
      	'details' => $details
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
