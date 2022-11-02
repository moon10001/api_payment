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
      $unitIds = [];
      
      if ($request->unit_id) {
        $vaCodes = collect($request->unit_id)->map(function($item) {
          return $item['va_code'];
        });
        $unitIds = DB::connection('auth')->table('units')
        ->select('id')
        ->whereIn('va_code', $vaCodes);
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
          $results = DB::connection('academics')->table('classrooms')
          ->select('classrooms.id', 'levels.name as level', 'classrooms.name as classroom')
          ->join('levels', 'levels.id', 'classrooms.levels_id')
          ->where('classrooms.organizations_id', 3)
          ->whereIn('units_id', $unitIds)
          ->get();
          
          $options = $results->map(function ($item, $key) {
          	return [
          		'value' => $item->id, 
          		'label' => $item->classroom
          	];
          });
          
          return response()->json($options);
        default:
          return response();
    }
  }
}
