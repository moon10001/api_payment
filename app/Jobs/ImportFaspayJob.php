<?php

namespace App\Jobs;

use JSON;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
use App\Jobs\ExportPGToJournalsJob;
use Illuminate\Support\Facades\Log;

class ImportFaspayJob extends Job
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
    
    public $tries = 3;
    public $timeout = 900;

    private function getTrInvoice($id, $tempsId = '') {
      $trInvoice = DB::table('tr_invoices')
      ->select('id', 'nominal', 'payments_date')
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

    private function fileHasBeenImported($filename) {
    	$res = DB::table('faspay_import_log')
    	->where('filename', $filename)
    	->where('status', 'PROCESSED')
    	->get();
      	echo('Imported: ' .$res->count()."\n");
      	app('log')->channel('slack')->info($filename.' import count: '.$res->count());        
    	return $res->count() >= 1;
    }

    public function logMT940File($filename, $status = 'READING', $message = '') {
      DB::table('faspay_import_log')->updateOrInsert([
        'filename' => $filename,
      ], [
        'filename' => $filename,
        'processed_at' => Carbon::now(),
        'status' => $status,
        'error_log' => '',
        'updated_at' => Carbon::now(),
      ]);
    }

    public function updateTrFaspay($data) {
        $update = DB::table('tr_faspay')
        ->where('id', $data['bill_id'])
        ->update([
          'settlement_date' => $data['settlement_date'],
          'settlement_total' => $data['settlement_total'],
        ]);
    }

    public function handle() {
      $fileCount = 0;
      $totalRowCount = 0;
      $response = [];
      $files = [];
      app('log')->channel('slack')->info(date('Y-m-d h:i:s') . ' - Processing Faspay');
        
      try {
        $invoicesIds = [];
        foreach(Storage::disk('faspay')->files('/') as $filename) {
          $rowCount = 0;
          $file = Storage::disk('faspay')->get($filename);
          if ($this->fileHasBeenImported($filename)) {
            app('log')->channel('slack')->error($filename . ' has been imported before.');            
            continue;
          }
          $this->logMT940File($filename);
          echo('Processing: '.$filename."\n");
          array_push($files, $filename);

          $fileCount++;
          $data = [];
          echo storage_path('app/faspay/'.$filename ."\n");
          $spreadsheet = IOFactory::load(storage_path('app/faspay/'.$filename));
          $worksheet = $spreadsheet->getSheetByName('Detail');
		  app('log')->channel('slack')->info('Number of rows in '. $worksheet->getTitle() .' : '. $worksheet->getHighestRow());
		  
          foreach ($worksheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            if ($rowIndex < 5) {
              continue;
            }

            $billId = $worksheet->getCell('P'.$rowIndex)->getFormattedValue();
            $settlementDate = $worksheet->getCell('N'.$rowIndex)->getFormattedValue();
            $settlementTotal = $worksheet->getCell('O'.$rowIndex)->getFormattedValue();

            if ($billId == '') {
              continue;
            }

            array_push($data, [
              'bill_id' => $billId,
              'settlement_date' => date('Y-m-d h:i:s', strtotime($settlementDate)),
              'settlement_total' => floatval(str_replace(',', '', $settlementTotal)),
            ]);
          }

          DB::transaction(function() use(&$data, $filename, $rowCount, $totalRowCount) {
            try {
              foreach($data as $row) {
                $this->updateTrFaspay($row);
                $rowCount++;
              }
            } catch (Exception $e) {
              error_log('Failed processing : '. $filename);
              $this->logMT940File($filename, 'FAILED', $e->getMessage());
              app('log')->channel('slack')->error('FAILED - '.$filename);        
              throw $e;
            } finally {
              echo('Finished processing: '. $filename."\n");
              echo('Rows processed: '. $rowCount."\n");
              $totalRowCount += $rowCount;
              $this->logMT940File($filename, 'PROCESSED');
            }
          });
        }
        if ($fileCount > 0) {
        	dispatch(new ExportPGToJournalsJob);
        }
      } catch (Exception $e) {
      	app('log')->channel('slack')->error($e->getMessage());
        error_log('Failed Reading'."\n".$e->getMessage() );
      }
      echo('==============================================='."\n");
      echo('Files processed: '. $fileCount."\n");
      echo('Rows processed : '. $totalRowCount."\n");
    }
}
