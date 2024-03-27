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
      $endYear = str_pad(intval($startYear) + 1, 2, "0");
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
      
     $periodeMonth = [7, 8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6];

      $student = DB::table('ms_temp_siswa')
      ->select('id', 'name', 'class', 'paralel', 'jurusan')
      ->where('id', $request->va_code)
      ->first();

      $invoicesRes = DB::table('tr_invoices')
      ->select(
        '*',
        DB::raw('MONTH(periode_date) as periode_month'),
        DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date')
      )
      ->whereIn('periode', $periode)
      ->where('temps_id', $request->va_code)
      ->get();
      
      $invoices = [];
      
	  foreach($periodeMonth as $month) {
	  	$res = $invoicesRes->where('periode_month', $month)->first();
	  	if ($res) {
	  		array_push($invoices, $res);
	  	}
	  }
	  

      $liabilities = [];
      $lastInvoice = [];
      $lastPaidInvoice = $invoicesRes->whereNotNull('payments_date')->last();
      if ($lastPaidInvoice) {
      	$nextPaymentMonth = $lastPaidInvoice->periode_month == 12 ? 1 : intval($lastPaidInvoice->periode_month) + 1;
      	$lastInvoice= $invoicesRes->whereNull('payments_date')->where('periode_month', '=', $nextPaymentMonth)->first();
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
        'periodeMonth' => $periodeMonth,
        'liabilities' => $liabilities,
		'lastInvoice' => $lastInvoice,
		'lastPaidInvoice' => $lastPaidInvoice,
      ]);
    }

    public function recap(Request $request) {
      $vaCodes = [];
      $unitIds = [];
      $classroomsId = $request->class;
      $year = str_pad($request->year, 2, "0", STR_PAD_LEFT);
      $year2 = str_pad(intval($year) + 1, 2, "0", STR_PAD_LEFT);
      $startYear = '2' . str_pad($year, 3, "0", STR_PAD_LEFT);
      $endYear = intval($startYear) + 1;
      $periodsId = '';
      $periode = [
        '07'.$year,
        '08'.$year,
        '09'.$year,
        '10'.$year,
        '11'.$year,
        '12'.$year,
        '01'.$year2,
        '02'.$year2,
        '03'.$year2,
        '04'.$year2,
        '05'.$year2,
        '06'.$year2,
      ];
      if ($request->unit_id) {
        $vaCodes = collect($request->unit_id)->map(function($item) {
          return $item['va_code'];
        });
        $units = DB::connection('auth')->table('units')
        ->select('*')
        ->whereIn('va_code', $vaCodes)
        ->get();
        $unitIds = $units->map(function($item) {
        	return $item->id;
        });
      }
      $classArray = $request->class;
      $class = '';
      $paralel = null;
      $jurusan = null;

      $periodsId = DB::connection('academics')->table('periods')
        ->where('name_period', 'like', $startYear.'%')
        ->whereIn('units_id', $unitIds)
        ->where('organizations_id', 3)
        ->first();	  
          
      $students = DB::connection('academics')->table('student_profile')
      ->select('no_va', 'no_va as id', DB::raw('CONCAT(first_name, " ", last_name) as name'))
      ->join('class_div', 'class_div.students_id', 'student_profile.id')
      ->whereIn('student_profile.units_id', $unitIds)
      ->where('class_div.classrooms_id', $classroomsId)
      ->where('periods_id', $periodsId->id)
      ->where('class_div.is_approve', 1)
      ->orderBy('student_profile.first_name', 'asc')
      ->orderBy('student_profile.last_name', 'asc')
      ->get();
      
      $monthlyTotal = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

      $expected = DB::table('tr_invoices')
      ->select(
          '*',
          'payments_date',
          DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date_formatted'),
          DB::raw('DATE_FORMAT(payments_date,"%m") as payment_month'),
          DB::raw('YEAR(payments_date) as payment_year'),
          DB::raw('(SELECT SUM(nominal) FROM tr_payment_details WHERE invoices_id = tr_invoices.id) as total')
      )
      ->whereBetween('periode_date', [$startYear.'-07-01', $endYear.'-06-31'])
      ->whereIn('temps_id', $students->pluck('no_va')->toArray())
      ->where('tr_invoices.id','like','INV-%')
      ->orderBy('periode_date', 'ASC')
      ->get();
                                                                                  
      // $invoices = DB::table('tr_invoices')
      //   ->select(
      //     '*',
      //     'payments_date',
      //     DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date_formatted'),
      //     DB::raw('DATE_FORMAT(payments_date,"%m") as payment_month'),
      //     DB::raw('YEAR(payments_date) as payment_year'),
      //     DB::raw('(SELECT SUM(nominal) FROM tr_payment_details WHERE invoices_id = tr_invoices.id) as total')
      //   )
      //   ->whereRaw('DATE(payments_date) between ? and ?', [$startYear.'-07-01', $endYear.'-06-31'])
      //   ->whereIn('temps_id', $students->pluck('no_va')->toArray())
      //   ->where('tr_invoices.id','like','INV-%')
      //  ->get();
      $invoices = collect(DB::select('
        SELECT
          tr_invoices.*,
          mt940.payment_date,
          tr_faspay.settlement_date,
          @temp_date := COALESCE(mt940.payment_date, tr_faspay.settlement_date) as payments_date,
          DATE_FORMAT(@temp_date, "%d-%m-%y") as payment_date_formatted,
          DATE_FORMAT(@temp_date, "%m") as payment_month,
          YEAR(@temp_date) as payment_year,
          (SELECT SUM(nominal) FROM tr_payment_details WHERE invoices_id = tr_invoices.id) as total
        FROM 
          tr_invoices
        LEFT JOIN mt940 
        ON 
          mt940.id = tr_invoices.mt940_id AND DATE(mt940.payment_date) between "'. $startYear.'-07-01" and "'. $endYear .'-06-31"
        LEFT JOIN tr_faspay
        ON
          tr_faspay.id = tr_invoices.faspay_id AND DATE(tr_faspay.settlement_date) between "'. $startYear.'-07-01" and "'. $endYear.'-06-31"
        WHERE 
        tr_invoices.temps_id IN ('. join(',', $students->pluck('no_va')->toArray()) .')
        AND tr_invoices.id like "INV-%"
      '));
      $outstanding = DB::table('tr_invoices')
      ->select(
        DB::raw('SUM(nominal) as total'),
        'temps_id'
      )
      ->whereIn('temps_id', $students->pluck('no_va')->toArray())
      ->where('periode_date', '<=', $endYear.'-06-01')
      ->whereNull('payments_date')
      ->where('id', 'like', 'INV-%')
      ->groupBy('temps_id')
      ->get();

      foreach($students as $key => &$item) {
        $filteredInvoices = $invoices->where('temps_id', $item->no_va);
        $filteredExpected = $expected->where('temps_id', $item->no_va);
        $filteredOutstanding = $outstanding->where('temps_id', $item->no_va);
        $mappedInvoices = $filteredInvoices->where('payments_date', '!=', null)
        ->whereBetween('payment_year', [$startYear, $endYear]) 
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

		$item->startYear = $startYear;
		$item->endYear = $endYear;
		$item->filteredInvoices = $filteredInvoices;
        $item->amount = $filteredExpected->isNotEmpty() ? $filteredExpected->first()->nominal : 0;
        $item->invoices = $mappedInvoices;
        $item->total_invoices = $filteredExpected->sum('total');
        $item->total_payments = $mappedInvoices->sum();
        $item->diff = $item->total_invoices - $item->total_payments;
        $item->total_outstanding = !$filteredOutstanding->isEmpty() ? $filteredOutstanding->sum('total') : 0;
      };

      return response()->json([
        'data' => $students,
        'outstanding' => $outstanding,
        'periods' => $periodsId,
        'total_monthly' => $monthlyTotal,
        'total_payments' => $students->sum('total_payments'),
        'total_invoices' => $students->sum('total_invoices'),
        'total_outstanding' => $students->sum('total_outstanding'),
        'total_diff' => $students->sum('diff'),
      ]);
    }
}
