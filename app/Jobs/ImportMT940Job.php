<?php

namespace App\Jobs;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
use App\Jobs\UpdateTransactionsTableJob;

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
    
    public $timeout = 900;

    private function getTrInvoice($id, $tempsId = '') {
      $trInvoice = DB::table('tr_invoices')
      ->select('id', 'nominal', 'payments_date', 'collectible_name')
      ->where('id', $id)
      ->first();

      if((empty($trInvoice) || !$trInvoice) && !empty($tempsId)) {
        $trInvoice = DB::table('tr_invoices')
        ->select('nominal', 'payments_date')
        ->where('temps_id', $tempsId)
        ->first();
      }
      return $trInvoice;
    }

    private function updateTrInvoice($id, $paymentDate) {
//      echo('Updating TR INVOICE'."\n");
//      echo('---ID          : '.$id."\n");
//      echo('---Payment Date: '.$paymentDate."\n");

      return DB::table('tr_invoices')
      ->where('id', $id)
      ->whereNull('payments_date')
      ->update([
        'payments_date' => $paymentDate,
        'updated_at' => date('Y-m-d h:i:s')
      ]);
    }

    private function insertTrInvoiceDetails($invoiceId, $data) {
      //echo('Inserting Invoice Details'."\n");

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
      $toMonth = substr($data['periode_to'], 0, 2);
      $fromYear = substr($data['periode_from'], 2, 2);
      $toYear = substr($data['periode_to'], 2, 2);
      $fromTimestamp = mktime(0, 0, 0, $fromMonth, 1, '20'.$fromYear);
      $toTimestamp = mktime(0, 0, 0, $toMonth, 1, '20'.$toYear);

/*      echo('Inserting MT940'."\n");
      echo('---VA          : '.$data['va']."\n");
      echo('---Temps ID    : '.$data['temps_id']."\n");
      echo('---Payment Date: '.$data['payments_date']."\n");
      echo('---Periode From: '.date('Y-m-d', $fromTimestamp)."\n");
      echo('---Periode To  : '.date('Y-m-d', $toTimestamp)."\n");
      echo('---Nominal     : '.$data['nominal']."\n");
      echo('---Diff        : '.$data['diff']."\n");
      echo('---Mismatch    : '.$data['mismatch']."\n");
*/
      $id = DB::table('mt940')
      ->insertGetId([
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
      return $id;
    }

    private function fileHasBeenImported($filename) {
    	$res = DB::table('mt940_import_log')
    	->where('filename', $filename)
    	->where('status', 'PROCESSED')
    	->get();
      //echo('Imported: '.($res->count() >= 1)."\n");
    	return $res->count() >= 1;
    }
    
    public $tries = 3;

    public function logMT940File($filename, $status = 'READING', $message = '') {
      DB::table('mt940_import_log')->updateOrInsert([
        'filename' => $filename,
      ], [
        'filename' => $filename,
        'processed_at' => Carbon::now(),
        'status' => $status,
        'error_log' => $message
      ]);
    }

    public function insert($mt940) {
        foreach($mt940 as $data) {
            $trInvoice = $this->getTrInvoice($data['tr_invoices_id'], $data['temps_id']);
            $trInvoiceIds = [];
            if ($trInvoice && !empty($trInvoice)) {
              array_push($trInvoiceIds, $trInvoice->id);
              $sum = $trInvoice->nominal;
              $this->updateTrInvoice($data['tr_invoices_id'], $data['payments_date']);
              $this->insertTrInvoiceDetails($data['tr_invoices_id'], $data);
            }

            if ($data['periode_to'] !== $data['periode_from']) {
              $fromMonth = substr($data['periode_from'], 0, 2);
              $toMonth = substr($data['periode_to'], 0, 2);
              $fromYear = substr($data['periode_from'], 2, 2);
              $toYear = substr($data['periode_to'], 2, 2);
              $fromTimestamp = mktime(0, 0, 0, $fromMonth, 1, '20'.$fromYear);
              $toTimestamp = mktime(0, 0, 0, $toMonth, 1, '20'.$toYear);

              $iyear = intval($fromYear);
              $imonth = intval($fromMonth);
              
              while(str_pad($iyear, 2, 0, STR_PAD_LEFT).str_pad($imonth, 2, 0, STR_PAD_LEFT) != $toYear.$toMonth) {
                $imonth ++;
                if ($imonth > 12) {
                  $imonth = 1;
                  $iyear ++;
                }
                $id = 'INV-' . $data['temps_id'] . str_pad($iyear, 2, 0, STR_PAD_LEFT) . str_pad($imonth, 2, 0, STR_PAD_LEFT);
                
                $trInvoice = $this->getTrInvoice($id);
                if ($trInvoice) {
                  array_push($trInvoiceIds, $trInvoice->id);
                  $sum = $trInvoice->nominal + $sum;
                  $this->updateTrInvoice($id, $data['payments_date']);
                  $this->insertTrInvoiceDetails($id, $data);
                }
              }
              /*$datediff = round(($toTimestamp - $fromTimestamp)/(60 * 60 * 24 * 30));
              $currentTimestamp = $fromTimestamp;
              for ($i = 1; $i <= $datediff; $i++) {
                $currentTimestamp = strtotime('next month', $currentTimestamp);
                $periode = date('my', $currentTimestamp);
                $id = 'INV-' . $data['temps_id'] .date('y', $currentTimestamp). date('m', $currentTimestamp);

                $trInvoice = $this->getTrInvoice($id);
                if ($trInvoice) {
                  array_push($trInvoiceIds, $trInvoice->id);
                  $sum = $trInvoice->nominal + $sum;
                  $this->updateTrInvoice($id, $data['payments_date']);
                  $this->insertTrInvoiceDetails($id, $data);
                }
              }*/
            }

            $data = array_merge($data, [
              'diff' => floatval($sum) - floatval($data['nominal']),
              'mismatch' => floatval($sum) !== floatval($data['nominal'])
            ]);
            $id = $this->insertMT940($data);
            $updateResult = DB::table('tr_invoices')->whereNull('mt940_id')->whereIn('id', $trInvoiceIds)->update(['mt940_id' => $id]);

            if($updateResult === 0) {
            	DB::table('mt940_duplicates')
            	->insert([
            		'mt940_id' => $id,
            		'va' => $data['va'],
            		'temps_id' => $data['temps_id'],
            		'created_at' => Carbon::now(),
            		'updated_at' => Carbon::now()
            	]);
            }
        }
    }

    public function handle() {
      $fileCount = 0;
      $rowCount = 0;
      $response = [];
      $files = [];

      app('log')->channel('slack')->info('Processing MT940');
      echo ("Processing MT940");
              
      try {
        $invoicesIds = [];
        foreach(Storage::disk('mt940')->files('/') as $filename) {
          $file = Storage::disk('mt940')->get($filename);
          if ($this->fileHasBeenImported($filename)) {
            app('log')->channel('slack')->error($filename . ' has been imported before.');
            continue;
          }
          $this->logMT940File($filename);
          //echo('Processing: '.$filename."\n");
          $mt940 = [];
          $fileDate = substr($filename, 18, 8);
          $paymentYear = substr($fileDate, 0, 4);
          $paymentMonth = substr($fileDate, 4, 2);
          $paymentDate = substr($fileDate, 6, 2);
          $payment_date = date('Y-m-d h:i:s', mktime(0, 0, 0, $paymentMonth, $paymentDate, $paymentYear));
          array_push($files, $filename);

          $fileCount++;
          $data = [];

          DB::transaction(function() use(&$rowCount, &$response, &$file, &$mt940, $payment_date, &$data, $filename) {
            try {
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
                    $coa = substr($periode, 0, 5);
                    $prmPayment = DB::table('prm_payments')
                    	->where('coa', $coa)
                    	->where('is_routine', 0)
                    	->where('is_active', 1)
                    	->first();
                    $id = $prmPayment->nick_name.'-'.$tempsId.$term;
					$periode_to = $coa.$term;
					$periode_from = $coa.$term;
                  }

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
                  $lineContent = substr($line, 4);
                  $data = [
                    'payments_date' => $payment_date,
                    'nominal' => substr(substr($lineContent, 7), 0, -5),
                  ];
                }
              }
              $this->insert($mt940);
            } catch (Exception $e) {
              error_log('Failed processing : '. $filename);
              app('log')->channel('slack')->error($e->message());              
              throw $e;
            } finally {
              $this->logMT940File($filename, 'PROCESSED');
            }
          });
        }
        if ($fileCount > 0) {
        	dispatch(new UpdateTransactionsTableJob);
        }
      } catch (Exception $e) {
        error_log('Failed Reading'."\n" );
        
        app('log')->channel('slack')->error($e->message());        
      }
      //echo('==============================================='."\n");
      //echo('Files processed: '. $fileCount."\n");
      //echo('Rows processed : '. $rowCount."\n");
    }
    
    public function retryUntil() {
    	return now()->addMinutes(10);
    }
}
