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
      $periode = [];
      for ($i = 1; $i <= 12; $i++) {
        if ($i < 7 ) {
          array_push($periode, $i.$endYear);
        } else {
          array_push($periode, $i.$startYear);
        }
      }
      $results = DB::table('tr_invoices')
      ->select('*')
      ->whereIn('periode', $periode)
      ->where('temps_id', $request->va_code)
      ->get();
      var_dump(DB::getQueryLog());
      return response()->json($results);
    }
}
