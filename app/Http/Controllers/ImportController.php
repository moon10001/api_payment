<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use App\Jobs\UpdateTrInvoicesTableJob;
use Illuminate\Support\Facades\DB;

class ImportController extends BaseController
{
    protected $command;

    public function __construct(ConsoleOutput $consoleOutput) {
        $this->output = $consoleOutput;
    }

    private function getTrInvoice($id) {
      $trInvoice = DB::table('tr_invoices')
      ->select('nominal', 'payments_date')
      ->where('id', $id)
      ->first();
      return $trInvoice;
    }

    private function updateTrInvoice($id, $paymentDate) {
      return DB::table('tr_invoices')
      ->where('id', $id)
      ->whereNull('payments_date')
      ->update([
        'payments_date' => $paymentDate,
        'updated_at' => date('Y-m-d h:i:s')
      ]);
    }

    private function insertTrInvoiceDetails($invoiceId, $data) {
      return DB::table('tr_invoice_details')
      ->updateOrInsert([
        'invoices_id' => $invoiceId,
      ], [
        'invoices_id' => $invoiceId,
        'receipts_id' => $data['va'],
        'nominal' => $data['nominal'],
        'payments_date' => $data['payments_date'],
        'payments_type' => 'H2H',
        'nobukti' => $data['va'],
        'created_at' => date('Y-m-d h:i:s'),
        'updated_at' => date('Y-m-d h:i:s')
      ]);
    }

    private function insertMT940($data) {
      DB::table('mt940')
      ->updateOrInsert([
          'va' => $data['va'],
        ], [
        'va' => $data['va'],
        'temps_id' => $data['temps_id'],
        'periode_from' => $data['periode_from'],
        'periode_to' => $data['periode_to'],
        'payment_date' => $data['payments_date'],
        'nominal' => $data['nominal'],
        'mismatch' => $data['mismatch'],
        'diff' => $data['diff'],
        'created_at' => date('Y-m-d h:i:s'),
        'updated_at' => date('Y-m-d h:i:s')
      ]);
    }

    public function import() {
      DB::transaction(function() {
        try {
          foreach(Storage::disk('mt940')->files('/') as $filename) {
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
                $va = substr($lineContent, 17, 17);
                $tempsId = substr($va, 0, 9);
                $id = 'INV-' . $tempsId . date('ym', $fromTimestamp);
                $periode_to = date('my', $toTimestamp);
                $periode_from = date('my', $fromTimestamp);
                $sum = 0;

                $data = array_merge($data, [
                  'va' => $va,
                  'tr_invoices_id' => $id,
                  'temps_id' => $tempsId,
                  'periode_to' => $periode_to,
                  'periode_from' => $periode_from
                ]);

                $trInvoice = $this->getTrInvoice($id);
                if ($trInvoice) {
                  $sum = $trInvoice->nominal;
                  $this->updateTrInvoice($id, $data['payments_date']);
                  $this->insertTrInvoiceDetails($id, $data);
                }


                if ($periode_to !== $periode_from) {
                  $datediff = round(($toTimestamp - $fromTimestamp)/(60 * 60 * 24 * 30));
                  $currentTimestamp = $fromTimestamp;
                  for ($i = 1; $i <= $datediff; $i++) {
                    $currentTimestamp = strtotime('next month', $currentTimestamp);
                    $periode = date('my', $currentTimestamp);
                    $id = 'INV-' . $tempsId .date('y', $currentTimestamp). date('m', $currentTimestamp);

                    $trInvoice = $this->getTrInvoice($id);
                    if ($trInvoice) {
                      $sum = $trInvoice->nominal + $sum;
                      $this->updateTrInvoice($id, $data['payments_date']);
                      $this->insertTrInvoiceDetails($id, $data);
                    }
                  }
                }

                $data = array_merge($data, [
                  'diff' => floatval($sum) - floatval($data['nominal']),
                  'mismatch' => floatval($sum) !== floatval($data['nominal'])
                ]);

                $this->insertMT940($data);
              }
              if (str_starts_with($line, ':61:')) {
                $lineContent = substr($line, 4);
                $paymentYear = '20'.substr($lineContent, 0, 2);
                $paymentMonth = substr($lineContent, 2, 2);
                $paymentDate = substr($lineContent, 4, 2);
                $payment_date = date('Y-m-d h:i:s', mktime(0, 0, 0, $paymentMonth, $paymentDate, $paymentYear));
                $data = [
                  'payments_date' => $payment_date,
                  'nominal' => substr(substr($lineContent, 7), 0, -5),
                ];
              }
            }
          }
        } catch (Exception $e) {
          throw $e;
        }
      });
    }
}
