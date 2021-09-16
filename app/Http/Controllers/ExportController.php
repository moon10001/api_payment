<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use App\Jobs\UpdateTrInvoicesTableJob;
use Illuminate\Support\Facades\DB;

class ExportController extends BaseController
{
    protected $command;

    public function __construct(ConsoleOutput $consoleOutput) {
        $this->output = $consoleOutput;
    }


    public function getReconciliatedData(Request $request) {
      $from = $request->from;
      $to = $request->to;
      $unitId = $request->unit_id;

      $data = [];

      $unitsVA = Cache::get('prm_va', function() {
        $res = DB::table('prm_va')
        ->where(function($q) use ($unitId) {
          if(isset($unitId)) {
            $q->where('unit_id', $unitId);
          }
        })->get();
        return $res;
      });

      foreach($unitsVA as $va) {
        $transactions = DB::table('daily_reconciled_reports')
        ->where(function($q) use ($from, $to) {
          if(isset($from)) {
            $q->where('mt940.payment_date', '>=', $from);
          }
          if(isset($to)) {
            $q->where('mt940.payment_date', '<=', $to);
          }
        })
        ->join('prm_payments', 'prm_payments.id', 'daily_reconciled_reports.prm_payments_id')
        ->where('units_id', $va->unit_id)
        ->orderBy('payment_date', 'ASC')
        ->get();

        if ($transactions->isNotEmpty()) {
          $grouped = $transactions->mapToGroups(function ($item, $key) {
            return [$item->payment_date => $item];
          });

          array_push($data, [
            'units_id' => $va->unit_id,
            'transactions' => $grouped,
          ]);
        }
      }

      return $data;
    }
}
