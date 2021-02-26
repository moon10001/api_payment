<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use App\Jobs\UpdateTrInvoicesTableJob;

class ImportController extends BaseController
{
    protected $command;

    public function __construct(ConsoleOutput $consoleOutput) {
        $this->output = $consoleOutput;
    }

    private function updateDatabase($records) {
      var_dump($records);
      foreach($records as $record) {
        $affected = DB::table('tr_invoices')
        ->where('id', $record['id'])
        ->where('periode', $record['periode'])
        ->where('temps_id', $record['temps_id'])
        ->where('academics_year', $record['academics_year'])
        ->whereNull('payments_date')
        ->update([
          'payments_date' => $record['payments_date']
        ]);
        if ($affected > 0) {
          DB::table('tr_invoice_details')
          ->insert([
            'invoices_id' => $record['id'],
            'receipts_id' => $record['id'],
            'nominal' => $record['nominal'],
            'payments_date' => $record['payments_date'],
            'payments_type' => 'H2H',
          ]);
        }
      }
    }

    public function import() {
      $records = [];
      $count = 0;

      foreach(Storage::disk('mt940')->files('/') as $filename) {
        // if ($count === 1) continue;
        // else $count++;
        // $this->output->writeln($filename);
        $file = Storage::disk('mt940')->get($filename);
        $data = [];
        foreach(explode(PHP_EOL, $file) as $line) {
          if (str_starts_with($line, ':86:UBP')) {
            $lineContent = substr($line, 7);
            $fromMonth = substr($lineContent, 26, 2);
            $toMonth = substr($lineContent, 30, 2);
            $fromYear = substr($lineContent, 28, 2);
            $toYear = substr($lineContent, 32, 2);
            $fromTimestamp = mktime(0, 0, 0, $fromMonth, 1, '20'.$fromYear);
            $toTimestamp = mktime(0, 0, 0, $toMonth, 1, '20'.$toYear);
            $fromDate = date('Y-m-d', $fromTimestamp);
            $toDate = date('Y-m-d', $toTimestamp);
            $datediff = round(($toTimestamp - $fromTimestamp)/(60 * 60 * 24 * 30));
            $va = substr($lineContent, 17);
            $tempsId = substr($va, 0, 9);
            $id = 'INV-' . $tempsId . date('ym', $fromTimestamp);
            $periode = date('my', $fromTimestamp);

            if ($datediff > 0) {
              $data['nominal'] = floatval($data['nominal']) / ($datediff+1);
              for ($i = 0; $i <= $datediff; $i++) {
                if ($i > 0) {
                  $currentTimestamp = strtotime('next month', $currentTimestamp);
                  $periode = date('my', $currentTimestamp);
                  //var_dump($currentTimestamp, $periode);
                  $id = 'INV-' . $tempsId .date('y', $currentTimestamp). date('m', $currentTimestamp);
                } else {
                  $currentTimestamp = $fromTimestamp;
                }
                $result = array_merge($data, [
                  'line' => $line,
                  'type' => substr($lineContent, 0, 4),
                  'companyCode' => substr($lineContent, 4, 5),
                  'language' => substr($lineContent, 9, 2),
                  'option' => substr($lineContent, 11, 6),
                  'va' => $va,
                  'id' => $id,
                  'temps_id' => $tempsId,
                  'range' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'diff' => $datediff,
                  ],
                  'periode' => $periode,
                  'nim' => substr($lineContent, 22, 4),
                  'unit' => substr($lineContent, 17, 3),
                  'academics_year' => '20'.substr($lineContent, 20, 2),
                ]);
                array_push($records, $result);
              }
            } else {
              $result = array_merge($data, [
                'line' => $line,
                'type' => substr($lineContent, 0, 4),
                'companyCode' => substr($lineContent, 4, 5),
                'language' => substr($lineContent, 9, 2),
                'option' => substr($lineContent, 11, 6),
                'va' => $va,
                'id' => $id,
                'temps_id' => $tempsId,
                'range' => [
                  'from' => $fromDate,
                  'to' => $toDate,
                  'diff' => $datediff,
                ],
                'periode' => $periode,
                'nim' => substr($lineContent, 22, 4),
                'unit' => substr($lineContent, 17, 3),
                'academics_year' => '20'.substr($lineContent, 20, 2),
              ]);
              array_push($records, $result);
            }
          }
          if (str_starts_with($line, ':61:')) {
            $lineContent = substr($line, 4);
            $paymentYear = '20'.substr($lineContent, 0, 2);
            $paymentMonth = substr($lineContent, 2, 2);
            $paymentDate = substr($lineContent, 4, 2);
            $payment_date = date('Y-m-d h:i:s', mktime(0, 0, 0, $paymentMonth, $paymentDate, $paymentYear));
            $data = [
              'payments_date' => $payment_date,
              'transactionType' => substr($lineContent, 6, 1),
              'nominal' => substr(substr($lineContent, 7), 0, -5),
            ];
          }
        }
      }

      dispatch(new UpdateTrInvoicesTableJob($records));
      return $records;
    }
}
