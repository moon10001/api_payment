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

      $lastInvoice= $invoices->whereNull('payments_date')->first();

      $liabilities = [];
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

      return response()->json([
        'student' => $student,
        'invoices' => $invoices,
        'liabilities' => $liabilities,
      ]);
    }

    public function recap(Request $request) {
      $invoices = DB::table('tr_invoices')
      ->select(
        '*',
        DB::raw('SUBSTR(periode, 1, 2) as periode_month'),
        DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date')
      )
      ->join('ms_temp_siswa', 'ms_temp_siswa.id' , '=', 'tr_invoices.temps.id')
      ->whereIn('periode', 'LIKE', '%'.$request->periode)
      ->get();
      return response()->json($invoices);
    }
}
