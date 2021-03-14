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
      $vaCodes = [];
      if ($request->unit_id) {
        $vaCodes = collect($request->unit_id)->map(function($item) {
          return $item['va_code'];
        });
      }
      switch($type) {
        case 'va-code':

          $results = DB::table('ms_temp_siswa')
          ->select('*')
          ->whereIn('units_id', $vaCodes)
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
        case 'class':
          $results = DB::table('ms_temp_siswa')
          ->select('class', 'paralel', 'jurusan')
          ->whereIn('units_id', $vaCodes)
          ->where('class', '<>', '')
          ->distinct()
          ->orderBy('class', 'ASC')
          ->orderBy('paralel', 'ASC')
          ->orderBy('jurusan', 'ASC')
          ->get();
          $options = $results->map(function ($item, $key) {
            return [
              'value' => $item->class .'_'. $item->paralel .'_'. $item->jurusan,
              'label' => $item->class .' '. $item->paralel .' '. $item->jurusan
            ];
          });
          return response()->json($options);
        default:
          return response();
    }
  }
}
