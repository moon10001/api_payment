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
      $unitId = $request->va_code[0]['unit_id'];
      $coa = $request->coa;
      if (isset($request->year)) {
        $year = $request->year;
      }
      $prmPayments = $this->getPrmPayments();
      $paymentId = $prmPayments->where('coa', '=', $coa)->pluck('id')->first();

	  $outstanding = DB::table('tr_invoices')
      ->selectRaw('
        YEAR(periode_date) as periode_year
      ')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->whereBetween('tr_invoices.periode_date', [($year-5).'-7-1', $year.'-6-30'])
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.id', 'like', 'INV-'.$va.'%')
      ->whereNotNull('tr_invoices.periode_date')
      ->groupBy(DB::raw('YEAR(periode_date)'))
      ->distinct()
      ->get();

      $outstandingDetails = DB::table('tr_invoices')
      ->selectRaw('
        SUM(tr_payment_details.nominal) as total_nominal,
        MONTH(periode_date) as periode_month,
        YEAR(periode_date) as periode_year,
        periode_date,
        tr_payment_details.payments_id,
        tr_invoices.payments_date,
        tr_invoice_details.payments_type
      ')
      ->join('tr_invoice_details', 'tr_invoice_details.invoices_id', 'tr_invoices.id')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->where('tr_invoices.periode_date', '>=', ($year-5).'-7-1')
      ->where('tr_invoices.periode_date', '<=', $year.'-6-30')
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.id', 'like', 'INV-'.$va.'%')
      ->groupBy(DB::raw('YEAR(periode_date), MONTH(periode_date)'), 'payments_type')
      ->get();

      $invoices = DB::table('tr_invoices')
      ->selectRaw('
        YEAR(periode_date) as periode_year,
        MONTH(periode_date) as periode_month,
        SUM(tr_payment_details.nominal) as total_invoice
      ')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
	  ->whereBetween('tr_invoices.periode_date', [$year.'-7-1', ($year+1).'-6-30'])
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.id', 'like', 'INV-'.$va.'%')
      ->groupBy(DB::raw('MONTH(periode_date)'))
      ->get();
      
      $paymentH2H = DB::connection('finance_db')->table('journal_logs')
      ->selectRaw('
      	YEAR(date) as periode_year,
      	MONTH(date) as periode_month,
      	SUM(IFNULL(credit, 0)) as total_paid
      ')
      ->whereBetween('date', [$year.'-07-01', ($year+1).'-06-30'])
      ->where('journal_number', 'like', 'H2H%')
      ->where('code_of_account', $coa)
      ->where('units_id', $unitId)
      ->groupBy(DB::raw('MONTH(date)'))
      ->get();	 
      
	  $paymentPG = DB::connection('finance_db')->table('journal_logs')
      ->selectRaw('
        YEAR(date) as periode_year,
        MONTH(date) as periode_month,
        SUM(IFNULL(credit, 0)) as total_paid
      ')
      ->whereBetween('date', [$year.'-07-01', ($year+1).'-06-30'])
      ->where('journal_number', 'like', 'PG%')
      ->where('code_of_account', $coa)
      ->where('units_id', $unitId)
      ->groupBy(DB::raw('MONTH(date)'))
      ->get();
                                                                  
      $paymentOffline = DB::connection('finance_db')->table('journal_logs')
      ->selectRaw('
        YEAR(date) as periode_year,
        MONTH(date) as periode_month,
        SUM(IFNULL(credit, 0)) as total_paid
      ')
      ->whereBetween('date', [$year.'-07-01', ($year+1).'-06-30'])
      ->where('journal_number', 'not like', 'H2H%')
      ->where('journal_number', 'not like', 'PG%')
      ->where('code_of_account', $coa)
      ->where('units_id', $unitId)
      ->groupBy(DB::raw('MONTH(date)'))
      ->get();
                                                                                                                                           	  
      $details = DB::table('tr_invoices')
      ->selectRaw('
        tr_payment_details.nominal as total_nominal,
        tr_payment_details.payments_id,
        MONTH(tr_invoices.periode_date) as periode_month,
        tr_invoice_details.payments_type,
        tr_invoices.payments_date
      ')
      ->join('tr_invoice_details', 'tr_invoice_details.invoices_id', 'tr_invoices.id')
      ->join('tr_payment_details', 'tr_payment_details.invoices_id', 'tr_invoices.id')
      ->whereBetween('tr_invoices.periode_date', [$year.'-7-1', ($year+1).'-6-30'])
      ->where('tr_payment_details.payments_id', $paymentId)
      ->where('tr_invoices.id', 'like', 'INV-'.$va.'%')
	  ->get();

      $outstandingData = [];
      foreach($outstanding as $o) {
        $year = $o->periode_year;
        $title = 'TA '.$year.'/'.($year+1);
        $filtered = $outstandingDetails->where('periode_date', '>=', $year.'-07-01')->where('periode_date', '<=', ($year+1).'-06-30');
        $totalInvoice = $filtered->sum('total_nominal');
        $totalPayment = $filtered->whereNotNull('payments_date')->sum('total_nominal');	
        
        $outstandingData[$title] = [
          'filtered' => $filtered->toArray(),
          'total' => $totalInvoice,
          'outstanding' => $totalInvoice - $totalPayment,
          'h2h' => $filtered->where('payments_type', 'H2H')->sum('total_nominal'),
          'pg' => $filtered->where('payments_type', 'Faspay')->sum('total_nominal'),
          'offline' => $filtered->where('payments_type', 'Offline')->sum('total_nominal'),
          'totalPayment' => $totalPayment
        ];
      }

  	  $res = collect([]);
      $months = ['7', '8', '9', '10', '11', '12', '1', '2', '3', '4', '5', '6'];
      $totalInvoice = $details->sum('total_nominal');
      $totalH2H = $paymentH2H->sum('total_paid');
      $totalPG = $paymentPG->sum('total_paid');
      $totalOffline = $paymentOffline->sum('total_paid');
      $totalPaid = $totalH2H + $totalPG + $totalOffline;
  	  $summary=[
  	  	'total' => $totalInvoice,
  	  	'outstanding' => 0,
  	  	'h2h' => $totalH2H,
  	  	'pg' => $totalPG,
  	  	'offline' => $totalOffline,
  	  	'totalPayment' => $totalPaid,
  	  ];

  	  $summary['outstanding'] = $totalInvoice - $totalPaid;
      foreach($months as $m) {
        $filtered = $invoices->where('periode_month', $m)->first();
        $filteredH2H = $paymentH2H->where('periode_month', $m)->first();
        $filteredPG = $paymentPG->where('periode_month', $m)->first();
        $filteredOffline = $paymentOffline->where('periode_month', $m)->first();
        
        $totalH2H = isset($filteredH2H) ? $filteredH2H->total_paid : 0;
        $totalPG = isset($filteredPG) ? $filteredPG->total_paid : 0;
        $totalOffline = isset($filteredOffline) ? $filteredOffline->total_paid : 0;
        $totalPaid = $totalH2H + $totalPG + $totalOffline;
        
        $data = $res->where('month', '=', $m)->first();
        
        if (!$data) {
          $data = [
            'month' => $m,
            'total' => isset($filtered->total_invoice) ? $filtered->total_invoice : 0,
            'outstanding' => 0,
            'h2h' => 0,
            'pg' => 0,
            'offline' => 0,
            'totalPayment' => 0,
           ];
           $res->push($data);           
        }
        $data['h2h'] = $totalH2H;
        $data['pg'] = $totalPG;
        $data['offline'] = $totalOffline;
        $data['totalPayment'] = $totalPaid;
        $data['outstanding'] = $data['total'] - $totalPaid;
        
        $res->transform(function($item, $key) use($m, $data) {
        	if ($item['month'] == $m) {
        		$item = $data;
        	}
        	return $item;
        });
        
      }

      return [
      	'h2h' => $paymentH2H,
      	'pg' => $paymentPG,
      	'offline' => $paymentOffline,
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
