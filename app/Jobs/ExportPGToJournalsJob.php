<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ExportPGToJournalsJob extends Job
{
    private $date;
    private $units;
    private $bankCoa = '11301';
    private $reconciliationCoa = '12902';
    private $destinationUnit = 95;
    private $paymentCoa = [
      'Uang Sekolah' => 41301,
      'Uang Kegiatan' => 41501,
      'Uang Praktik' => 41501,
      'Uang Sarana' => 41601,
      'Uang Pangkal Asrama' => 41701,
      'Uang Bulanan Asrama' => 41702,
      'Uang ILP' => 41501,
      'Uang SSB' => 41501,
      'Uang Antar Jemput' => 42202,
      'Uang POMG' => 41801,
      'Uang Extra' => 41501,
      'Uang OSIS' => 41501,
      'Uang Pramuka' => 41501,
      'Uang Makan' => 41501,
      'Pel Kasih' => 42201,
      'Uang Komputer' => 41401,
      'Uang Pendaftaran' => 42101,
      'DPP' => 41101,
      'UPP' => 41201,
    ];
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($date = '')
    {
    	$this->units = DB::table('api_kliksekolah.prm_school_units')->get();
        if($date != '') {
          $this->date = $date;
        } else {
          $this->date = date('Y-m-d');
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      try {
        $this->logPGToJournal();
        $this->logDetailsToJournal();
        $this->logDiscountsToJournal();
        $this->handleOverpaid();
      } catch (Exception $e) {
        throw $e;
      }
    }
    
    public function logPGToJournal() {
      $transactions = collect(DB::select(DB::raw('
        SELECT 
          prm_school_units.id as units_id, 
          prm_school_units.name as unit_name, 
          sum(a.settlement_total) as nominal
        FROM 
          ypl_h2h.tr_faspay a 
        INNER JOIN (
          select * from ypl_h2h.tr_invoices
          WHERE faspay_id is not null
          group by faspay_id
        ) b ON a.id = b.faspay_id 
        INNER JOIN (
          SELECT * from api_kliksekolah.prm_va 
          GROUP BY va_code
        ) c ON c.va_code = SUBSTR(b.temps_id, 1, 3) 
        INNER JOIN api_kliksekolah.prm_school_units ON c.unit_id = prm_school_units.id
        WHERE 
          DATE(a.settlement_date) = "' . $this->date . '"
        GROUP BY prm_school_units.id
      ')));
      
      foreach($transactions as $item) {
      	$journalNumber = $this->generateJournalNumber($this->date, 95);
        $this->logJournal([
        	'journal_id' => 0,
            'journal_number' => $journalNumber,
            'date' => $this->date,
            'code_of_account' => '11301',
            'description' => 'Rekonsiliasi PG '.$item->unit_name,
            'credit' => $item->nominal,
            'debit' => null,
            'units_id' => 95,
            'countable' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        
        echo('Inserting '.$journalNumber);
        echo "\n";
        
        $journalNumber = $this->generateJournalNumber($this->date, $item->units_id);                                                                                 
        $this->logJournal([
           'journal_id' => 0,
           'journal_number' => $journalNumber,
           'date' => $this->date,
           'code_of_account' => '12902',
           'description' => 'Rekonsiliasi PG',
           'credit' => null,
           'debit' => $item->nominal,
           'units_id' => $item->units_id,
           'countable' => 1,
           'created_at' => Carbon::now(),
           'updated_at' => Carbon::now(),
        ]);                             
        
        echo ('Inserting '. $journalNumber);
        echo "\n\n";                             	
      }
    }

    public function logDetailsToJournal() {
      $result = DB::select(DB::raw('
        SELECT 
          prm_va.unit_id,
          prm_payments.id,
          prm_payments.name,
          prm_payments.coa,
          SUM(tr_payment_details.nominal) as total
        FROM
          tr_invoices
        INNER JOIN
          tr_payment_details ON tr_payment_details.invoices_id = tr_invoices.id
        INNER JOIN
          tr_faspay on tr_faspay.id = tr_invoices.faspay_id
        INNER JOIN api_kliksekolah.prm_va ON prm_va.va_code = tr_invoices.va_code
        INNER JOIN prm_payments ON prm_payments.id = tr_payment_details.payments_id
        WHERE date(tr_faspay.settlement_date) = "' . $this->date . '"
        GROUP BY prm_va.unit_id, payments_id
      '));

      foreach($result as $data) {
        $timestamp = Carbon::now();
        $journalNumber = $this->generateJournalNumber($this->date, $data->unit_id);
        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $this->date,
          'code_of_account' => $data->coa,
          'description' => $data->name,
          'credit' => null,
          'debit' => $data->total,
          'units_id' => $data->unit_id,
          'countable' => 1,
          'created_at' => $timestamp,
          'updated_at' => $timestamp
        ]);
      }
    }
    public function logDiscountsToJournal() {
      $result = DB::select(DB::raw('
        SELECT 
          prm_va.unit_id,
          prm_payments.id,
          prm_payments.name,
          prm_payments.coa,
          SUM(tr_payment_discounts.nominal) as total
        FROM
          tr_invoices
        INNER JOIN
          tr_payment_discounts ON tr_payment_discounts.invoices_id = tr_invoices.id
        INNER JOIN
          mt940 on tr_faspay.id = tr_invoices.faspay_id
        INNER JOIN 
          api_kliksekolah.prm_va ON prm_va.va_code = tr_invoices.va_code
        INNER JOIN 
          prm_payments ON prm_payments.id = tr_payment_discounts.payments_id
        WHERE date(tr_faspay.settlement_date) = "' . $this->date . '"
        GROUP BY prm_va.unit_id, payments_id
      '));

      foreach($result as $data) {
        $timestamp = Carbon::now();
        $journalNumber = $this->generateJournalNumber($this->date, $data->unit_id);
        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $this->date,
          'code_of_account' => $data->coa,
          'description' => $data->name,
          'credit' => null,
          'debit' => $data->total,
          'units_id' => $data->unit_id,
          'countable' => 1,
          'created_at' => $timestamp,
          'updated_at' => $timestamp
        ]);
      }
    }

    public function handleOverpaid() {
      try {
      	$faspay = DB::table('tr_faspay')
      	->select('id', 'settlement_total')
      	->whereRaw('DATE(payment_date) = "?"', $this->date)
      	->get(); 
      	echo("BEGIN ==== ".$this->date."\n");
				$ids = $faspay->pluck('id');
		
        $trInvoices = DB::table('tr_invoices')
        ->select(DB::raw('SUM(nominal) as total_inv, faspay_id'))
        ->whereIn('faspay_id', $ids)
        ->groupBy('faspay_id')
        ->get();
		
		    $total = 0;
		      	
      	foreach($faspay as $row) {
      		$nominal = $row->nominal;
      		$filtered = $trInvoices->where('mt940_id', $row->id)->first();     		
      		if (!is_null($filtered)) {
      			$diff = floatval($nominal) - floatval($filtered->total_inv);
      			if ($diff > 0) {
      				$total += $diff;
				  	  echo($row->id."\n");
              echo($row->nominal."\n");
              echo($filtered->total_inv."\n");
              echo($total."\n\n");
            }      			
      		} else {
            $diff = floatval($nominal);
            $total += $diff;
            echo ($row->id."\n");
            echo ($row->nominal."\n");
            echo ($total."\n\n");
          }
      	}
        if ($total > 0) {
          $journalNumber =  $this->generateJournalNumber($this->date, 95);

          $this->logJournal([
            'journal_id' => 0,
            'journal_number' => $journalNumber,
            'date' => $this->date,
            'code_of_account' => '21701',
            'description' => 'Lebih bayar PG',
            'debit' => $total,
            'credit' => null,
            'units_id' => 95,
            'countable' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
          ]);
          
          $this->logJournal([
          	'journal_id' => 0,
          	'journal_number' => $journalNumber,
          	'date' => $this->date,
          	'code_of_account' => '11310',
          	'description' => 'Lebih bayar PG',
          	'debit' => null,
          	'credit' => $total,
          	'units_id' => 95,
          	'countable' => 1,
          	'created_at' => Carbon::now(),
          	'updated_at' => Carbon::now(),
          ]);
        } 
      } catch (Exception $e) {
      	throw $e;
      }
    }

    public function generateJournalNumber($date, $unitId) {
      $month = date('m', strtotime($date));
      $year = date('Y', strtotime($date));
      $shortYear = date('y', strtotime($date));
      $unit = $this->units->where('id', $unitId)->first();

      $counter = DB::connection('finance_db')->table('journal_logs')
      ->select('journal_number')
      ->whereRaw('MONTH(date) = ?', $month)
      ->whereRaw('YEAR(date) = ?', $year)
      ->whereRaw('units_id = ?', $unitId)
      ->where('journal_number', 'like', 'PG%')
      ->distinct()
      ->get();

      $count = $counter->count() + 1;

      $journalNumber = 'PG';
      $journalNumber = $journalNumber . $shortYear . str_pad($month, 2, '0', STR_PAD_LEFT) . str_pad($count, 3, '0', STR_PAD_LEFT) . $unit->unit_code;

      return $journalNumber;
    }

    private function logJournal($data) {
        DB::connection('finance_db')->table('journal_logs')->insert([
          'journals_id' => 0,
          'journal_number' => $data['journal_number'],
          'date' => $data['date'],
          'code_of_account' => $data['code_of_account'],
          'description' => $data['description'],
          'debit' => $data['credit'],
          'credit' => $data['debit'],
          'units_id' => $data['units_id'],
          'countable' => 1,
          'created_at' => $data['created_at'],
          'updated_at' => $data['updated_at']
        ]);
    }
}
