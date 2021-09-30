<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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

    public function get(Request $request) {
      DB::enableQueryLog();
      $startYear = $request->year;
      $endYear = intval($startYear) + 1;
      $periode = [
        '07'.$startYear,
        '08'.$startYear,
        '09'.$startYear,
        '10'.$startYear,
        '11'.$startYear,
        '12'.$startYear,
        '01'.$endYear,
        '02'.$endYear,
        '03'.$endYear,
        '04'.$endYear,
        '05'.$endYear,
        '06'.$endYear,
      ];

      $student = DB::table('ms_temp_siswa')
      ->select('id', 'name', 'class', 'paralel', 'jurusan')
      ->where('id', $request->va_code)
      ->first();

      $invoices = DB::table('tr_invoices')
      ->select(
        '*',
        DB::raw('SUBSTR(periode, 1, 2) as periode_month'),
        DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date')
      )
      ->whereIn('periode', $periode)
      ->where('temps_id', $request->va_code)
      ->get();

      $liabilities = [];
      $lastInvoice = [];
      $lastPaidInvoice = $invoices->whereNotNull('payments_date')->last();
      if ($lastPaidInvoice) {
      	$nextPaymentMonth = $lastPaidInvoice->periode_month == 12 ? 1 : intval($lastPaidInvoice->periode_month) + 1;
      	$lastInvoice= $invoices->whereNull('payments_date')->where('periode_month', '=', $nextPaymentMonth)->first();
      	if ($lastInvoice) {
        	$liabilities = DB::table('tr_payment_details')
        	->select(
          	'tr_payment_details.invoices_id',
          	'tr_payment_details.periode',
          	'tr_payment_details.nominal',
          	'prm_payments.name'
         	)
        	->join('prm_payments', 'tr_payment_details.payments_id', 'prm_payments.id')
        	->where('invoices_id', $lastInvoice->id)
        	->get();
      	}
      }

      return response()->json([
        'student' => $student,
        'invoices' => $invoices,
        'liabilities' => $liabilities,
	'lastInvoice' => $lastInvoice,
	'lastPaidInvoice' => $lastPaidInvoice,
      ]);
    }

    public function recap(Request $request) {
      $vaCodes = [];
      $year = $request->year;
      $startYear = $request->year;
      $endYear = intval($startYear) + 1;
      $periode = [
        '07'.$startYear,
        '08'.$startYear,
        '09'.$startYear,
        '10'.$startYear,
        '11'.$startYear,
        '12'.$startYear,
        '01'.$endYear,
        '02'.$endYear,
        '03'.$endYear,
        '04'.$endYear,
        '05'.$endYear,
        '06'.$endYear,
      ];
      if ($request->unit_id) {
        $vaCodes = collect($request->unit_id)->map(function($item) {
          return $item['va_code'];
        });
      }
      $classArray = $request->class;
      $class = '';
      $paralel = null;
      $jurusan = null;

      if(isset($classArray)) {
        $classArray = explode('_', $classArray);
        $class = $classArray[0];
        $paralel = isset($classArray[1]) ? $classArray[1] : null;
        $jurusan = isset($classArray[2]) ? $classArray[2] : null;
      }
      $students = DB::table('ms_temp_siswa')
      ->whereIn('units_id', $vaCodes)
      ->where('class', $class)
      ->where('paralel', $paralel)
      ->where('jurusan', $jurusan)
      ->orderBy('name')
      ->get();
      //return response()->json(['log' => DB::getQueryLog()]);
      $monthlyTotal = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
     
      $invoices = DB::table('tr_invoices')
      ->select(
        '*',
	      'payments_date',
        //DB::raw('SUBSTR(periode, 1, 2) as periode_month'),
        DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date_formatted'),
        DB::raw('DATE_FORMAT(payments_date,"%m") as payment_month'),
        DB::raw('(SELECT SUM(nominal) FROM tr_payment_details WHERE invoices_id = tr_invoices.id) as total')
      )
      ->whereBetween('periode_date', ['20'.$year.'-07-01', '20'.strval(intval($year)+1).'-06-01'])
      ->whereIn('temps_id', $students->pluck('id')->toArray())
      ->where('tr_invoices.id','like','INV-%')
      ->get();

	  foreach($students as $key => &$item) {
	  	$filteredInvoices = $invoices->where('temps_id', $item->id);
        $mappedInvoices = $filteredInvoices->where('payments_date', '!=', null)
        	->groupBy('payment_month')
        	->mapWithKeys(function($item, $key) use(&$monthlyTotal) {
          		$timestamp = strtotime($key);
	        	$month = $key;
	        	$values = $item->values();
          		$total = $values->sum('total');
        	  	$monthlyTotal[intval($month)-1] = $monthlyTotal[intval($month)-1] + $total;
         		return [
            		intval($month) => $total
          		];
        	});

	    $item->amount = $filteredInvoices->isNotEmpty() ? $filteredInvoices->first()->nominal : 0;
        $item->invoices = $mappedInvoices;
        $item->total_invoices = $filteredInvoices->sum('total');
        $item->total_payments = $mappedInvoices->sum();
        $item->diff = $item->total_invoices - $item->total_payments;
      };

      return response()->json([
        'data' => $students,
        'total_monthly' => $monthlyTotal,
        'total_payments' => $students->sum('total_payments'),
        'total_invoices' => $students->sum('total_invoices'),
        'total_diff' => $students->sum('diff'),
      ]);
    }
}
