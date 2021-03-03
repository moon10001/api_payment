<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OptionsController extends Controller
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

    public function get(Request $request, $type) {
      switch($type) {
        case 'va-code':
          $results = DB::table('ms_temp_siswa')
          ->select('*')
          ->where('units_id', $request->unit_id)
          ->where(function($q) use ($request) {
              $q->where('id', 'LIKE', $request->value.'%')
              ->orWhere('name', 'LIKE', '%'.$request->value.'%');
          })
          ->distinct()
          ->get();
          $options = $results->map(function ($item, $key) {
            return [
              'value' => $item->id,
              'label' => $item->id . ' - '. $item->name,
            ];
          });
          return response()->json($options);
        default:
          return response();
    }
  }
}
