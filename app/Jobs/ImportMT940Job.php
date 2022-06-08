<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ImportMT940Job extends Job
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use InteractsWithQueue, Queueable, SerializesModels;

    protected $command;
    private $output;

    public function __construct(ConsoleOutput $consoleOutput) {
        $this->output = $consoleOutput;
    }

    private function getTrInvoice($id, $tempsId = '') {
      $trInvoice = DB::table('tr_invoices')
      ->select('nominal', 'payments_date')
      ->where('id', $id)
      ->first();

      if(empty($trInvoice) || !$trInvoice) {
        $trInvoice = DB::table('tr_invoices')
        ->select('nominal', 'payments_date')
        ->where('temps_id', $tempsId)
        ->first();
      }
      return $trInvoice;
    }

    private function updateTrInvoice($id, $paymentDate) {
      $this->output->writeln('Updating TR INVOICE');
      $this->output->writeln('---ID          : '.$id);
      $this->output->writeln('---Payment Date: '.$data['payments_date']);

      return DB::table('tr_invoices')
      ->where('id', $id)
      ->whereNull('payments_date')
      ->update([
        'payments_date' => $paymentDate,
        'updated_at' => date('Y-m-d h:i:s')
      ]);
    }

    private function insertTrInvoiceDetails($invoiceId, $data) {
      $this->output->writeln('Inserting Invoice Details');

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
      $fromMonth = substr($data['periode_from'], 0, 2);
      $toMonth = substr($data['periode_to'], 2, 2);
      $fromYear = substr($data['periode_from'], 0, 2);
      $toYear = substr($data['periode_to'], 2, 2);
      $fromTimestamp = mktime(0, 0, 0, $fromMonth, 1, '20'.$fromYear);
      $toTimestamp = mktime(0, 0, 0, $toMonth, 1, '20'.$toYear);

      $this->output->writeln('Inserting MT940');
      $this->output->writeln('---VA          : '.$data['va']);
      $this->output->writeln('---Temps ID    : '.$data['temps_id']);
      $this->output->writeln('---Payment Date: '.$data['payments_date']);
      $this->output->writeln('---Nominal     : '.$data['nominal']);
      $this->output->writeln('---Diff        : '.$data['diff']);
      $this->output->writeln('---Mismatch    : '.$data['mismatch']);

      DB::table('mt940')
      ->insert([
        'va' => $data['va'],
        'temps_id' => $data['temps_id'],
        'periode_from' => $data['periode_from'],
        'periode_to' => $data['periode_to'],
        'periode_date_to' => date('Y-m-d', $toTimestamp),
        'periode_date_from' => date('Y-m-d', $fromTimestamp),
        'payment_date' => $data['payments_date'],
        'nominal' => $data['nominal'],
        'mismatch' => $data['mismatch'],
        'diff' => $data['diff'],
        'created_at' => date('Y-m-d h:i:s'),
        'updated_at' => date('Y-m-d h:i:s')
      ]);
    }

    private function fileHasBeenImported($filename) {
    	$res = DB::table('mt940_import_log')
    	->where('filename', $filename)
    	->where('status', 'PROCESSED')
    	->get();
      $this->output->writeln('Imported: '.$res->count() >= 1);
    	return $res->count() >= 1;
    }

    public function insert($mt940) {
        foreach($mt940 as $data) {
            $trInvoice = $this->getTrInvoice($data['tr_invoices_id'], $data['temps_id']);
            if ($trInvoice && !empty($trInvoice)) {
              $sum = $trInvoice->nominal;
              $this->updateTrInvoice($data['tr_invoices_id'], $data['payments_date']);
              $this->insertTrInvoiceDetails($data['tr_invoices_id'], $data);
            }

            if ($data['periode_to'] !== $data['periode_from']) {
              $fromMonth = substr($data['periode_from'], 0, 2);
              $toMonth = substr($data['periode_to'], 2, 2);
              $fromYear = substr($data['periode_from'], 0, 2);
              $toYear = substr($data['periode_to'], 2, 2);
              $fromTimestamp = mktime(0, 0, 0, $fromMonth, 1, '20'.$fromYear);
              $toTimestamp = mktime(0, 0, 0, $toMonth, 1, '20'.$toYear);

              $datediff = round(($toTimestamp - $fromTimestamp)/(60 * 60 * 24 * 30));
              $currentTimestamp = $fromTimestamp;
              for ($i = 1; $i <= $datediff; $i++) {
                $currentTimestamp = strtotime('next month', $currentTimestamp);
                $periode = date('my', $currentTimestamp);
                $id = 'INV-' . $data['temps_id'] .date('y', $currentTimestamp). date('m', $currentTimestamp);

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
    }

    public function handle() {
      $fileCount = 0;
      $rowCount = 0;
      $response = [];
      $files = [];
      $this->output->writeln('PROCESSING MT940 BEGINS');

      DB::transaction(function() use(&$fileCount, &$rowCount, &$response, &$files) {
        try {
          $invoicesIds = [];
          foreach(Storage::disk('mt940')->files('/') as $filename) {
            $mt940 = [];
            $file = Storage::disk('mt940')->get($filename);
            if ($this->fileHasBeenImported($filename)) {
              continue;
            }
            $this->output->writeln('Processing: '.$filename);
            $fileDate = substr($filename, 18, 25);
            $paymentYear = substr($fileDate, 0, 4);
            $paymentMonth = substr($fileDate, 5, 2);
            $paymentDate = substr($fileDate, 7, 2);
            $payment_date = date('Y-m-d h:i:s', mktime(0, 0, 0, $paymentMonth, $paymentDate, $paymentYear));
            array_push($files, $filename);

            $fileCount++;
            $data = [];

            foreach(explode(PHP_EOL, $file) as $line) {
              if (str_starts_with($line, ':86:UBP')) {
                $lineContent = substr($line, 7);
                $periode = substr($lineContent, 26, 8);
                $va = substr($lineContent, 17, 17);
                $tempsId = substr($va, 0, 9);
                $sum = 0;

                if (!str_starts_with($periode, 4)) {
                  $fromMonth = substr($periode, 0, 2);
                  $toMonth = substr($periode, 4, 2);
                  $fromYear = substr($periode, 2, 2);
                  $toYear = substr($periode, 6, 2);
                  $fromTimestamp = mktime(0, 0, 0, $fromMonth, 1, '20'.$fromYear);
                  $toTimestamp = mktime(0, 0, 0, $toMonth, 1, '20'.$toYear);
                  $id = 'INV-' . $tempsId . date('ym', $fromTimestamp);
                  $periode_to = date('my', $toTimestamp);
                  $periode_from = date('my', $fromTimestamp);
                } else {
                  $term = substr($periode, 5, 3);
                  $id = 'UPP-' . $tempsId . $term;
                  if(str_starts_with($periode, '41101')) {
                    $id = 'DPP-' . $tempsId . $term;
                    $periode_to = '41101'.$term;
                    $periode_from = '41101'.$term;
                  }else if(str_starts_with($periode, '42101')) {
                    $id = 'UPD-' . $tempsId . $term;
                    $periode_to = '42101'.$term;
                    $periode_from = '42101'.$term;
                  }
                }
                array_push($invoicesIds, $id);

                $data = array_merge($data, [
                  'va' => $va,
                  'tr_invoices_id' => $id,
                  'temps_id' => $tempsId,
                  'periode_to' => $periode_to,
                  'periode_from' => $periode_from
                ]);
                array_push($mt940, $data);
                array_push($response, $data);
                $rowCount++;
              }
              if (str_starts_with($line, ':61:')) {
                $data = [
                  'payments_date' => $payment_date,
                  'nominal' => substr(substr($lineContent, 7), 0, -5),
                ];
              }
            }
            $this->insert($mt940);
          }
        } catch (Exception $e) {

          error_log('Failed processing : ', $filename);
          DB::table('mt940_import_log')
          ->updateOrInsert([
            'filename' => $filename
          ], [
            'filename' => $filename,
            'processed_at' => Carbon::now(),
            'status' => 'FAILED',
            'error_log' => $e->getMessage(),
          ]);
          throw $e;
        }
      });
      $this->output->writeln('===============================================');
      $this->output->writeln('Files processed: ', $fileCount);
      $this->output->writeln('Rows processed : ', $rowCount);
    }
}
