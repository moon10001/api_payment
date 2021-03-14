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
      $vaCodes = [];
      if ($request->unit_id) {
        $vaCodes = collect($request->unit_id)->map(function($item) {
          return $item['va_code'];
        });
      }
      $class = $request->class;
      $year = $request->year;

      $students = DB::table('ms_temp_siswa')
      ->whereIn('units_id', $vaCodes)
      ->where('class', $class)
      ->get();

      $data = $students->map(function($item) {
        return [
          'student' => $item,
        ];
      });

      foreach($data as $key => $item) {
        $student = $item['student'];
        $invoices = DB::table('tr_invoices')
        ->select(
          '*',
          DB::raw('SUBSTR(periode, 1, 2) as periode_month'),
          DB::raw('DATE_FORMAT(payments_date, "%d-%m-%Y") as payments_date'),
          DB::raw('(SELECT SUM(nominal) FROM tr_payment_details WHERE invoices_id = tr_invoices.id) as total')
        )
        ->join('tr_invoice_details', 'tr_invoice_details.invoices_id', 'tr_invoices.id')
        ->where('periode', 'LIKE', '%'.$year)
        ->where('temps_id', $student->id)
        ->get();
        $data[$key]['invoices'] = $invoices;
      }
      return response()->json($data);
    }
}
