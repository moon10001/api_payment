<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateTransactionsTableJob extends Job
{
    private $date;
    private $from;
    private $to;
    private $unitId;
    private $unit;
    private $bankCoa = '11310';
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
    public function __construct($from = '', $to = '', $unitId = '', $date = '')
    {
        $this->from = $from;
        $this->to = $to;
        $this->unitId = $unitId;
        if($date != '') {
          $this->date = $date;
        } else {
          $this->date = date('Y-m-d');
        }
    }

    private function setUnit($unitId) {
      $this->unit = DB::connection('finance_db')->table('prm_school_units')->where('id', $unitId)->first();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      try {
        // $data = $this->getReconciliatedData();
        // foreach($data as $unitId => $payment) {
        //   $transactions = collect($payment);
        //   $date = $this->date;
        //   $this->setUnit($unitId);
        //   $this->createReconciliation($unitId, $date, $transactions);
        // }
        $this->logToJournal();
        $this->logDetailsToJournal();
        $this->logDiscountsToJournal();
        $this->handleMT940ForcedOK();
      } catch (Exception $e) {
        throw $e;
      }
    }


    public function getReconciliatedData() {
      $unitId = $this->unitId;

      $data = [];

      DB::enableQueryLog();
      $transactions = DB::connection('report_db')->table('daily_reconciled_reports')
        ->whereRaw('DATE(daily_reconciled_reports.payment_date) = ?', $this->date)
        ->orderBy('units_id', 'ASC')
        ->get();

      if ($transactions->isNotEmpty()) {
        $data = $transactions->mapToGroups(function ($item, $key) {
          return [$item->units_id => $item];
        });
      }

      return $data;
    }

    public function generateJournalNumber($date, $unitId) {
      $month = date('m', strtotime($date));
      $year = date('Y', strtotime($date));
      $shortYear = date('y', strtotime($date));
      $unit = DB::connection('finance_db')->table('prm_school_units')->where('id', $unitId)->first();

      $counter = DB::connection('finance_db')->table('journal_logs')
      ->select('journal_number')
      ->whereRaw('MONTH(date) = ?', $month)
      ->whereRaw('YEAR(date) = ?', $year)
      ->whereRaw('units_id = ?', $unitId)
      ->where('journal_number', 'like', 'H2H%')
      ->distinct()
      ->get();

      $count = $counter->count() + 1;

      $journalNumber = 'H2H';
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

    private function createTransaction($unitId, $date, $items) {
      $journal = [
        'journal_number' => $this->generateJournalNumber($date, $unitId),
        'journal_date' => $date,
        'units_id' => $unitId,
        'nominal' => $items->sum('nominal'),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now()
      ];

      $journalId = DB::connection('finance_db')->table('journal_h2h')
      ->insertGetId($journal);

      $details = [];
      foreach($items as $item) {
        $coa = $this->paymentCoa[$item->name];
        array_push($details, [
          'journal_h2h_id' => $journalId,
          'code_of_account' => $coa,
          'nominal' => $item->nominal,
          'created_at' => Carbon::now(),
          'updated_at' => Carbon::now()
        ]);
      }

      DB::connection('finance_db')->table('journal_h2h_details')->insert($details);
    }

    private function createReconciliation($unitId, $date, $items) {
      $sum = $items->sum('nominal');
      $timestamp = Carbon::now();
      $journalNumber = $this->generateJournalNumber($date, $unitId);
	    $prmPaymentList = DB::table('prm_payments')->get();
	  
      foreach($items as $item) {
        $prmPayment = $prmPaymentList->first(function ($value, $key) use ($item) { 
        	return $value->id == $item->prm_payments_id;
        });
        
        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $date,
          'code_of_account' => $item->coa,
          'description' => $prmPayment->name,
          'credit' => null,
          'debit' => $item->nominal,
          'units_id' => $unitId,
          'countable' => 1,
          'created_at' => $timestamp,
          'updated_at' => $timestamp
        ]);

        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $date,
          'code_of_account' => '12902',
          'description' => $prmPayment->name,
          'credit' => $item->nominal,
          'debit' => null,
          'units_id' => $unitId,
          'countable' => 1,
          'created_at' => $timestamp,
          'updated_at' => $timestamp,
        ]);
      }

      $journalNumber = $this->generateJournalNumber($date, 95);
      $this->logJournal([
        'journal_id' => 0,
        'journal_number' => $journalNumber,
        'date' => $date,
        'code_of_account' => '11310',
        'description' => 'Rekonsiliasi H2H '.$this->unit->name,
        'credit' => $sum,
        'debit' => null,
        'units_id' => 95,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);
      $this->logJournal([
        'journal_id' => 0,
        'journal_number' => $journalNumber,
        'date' => $date,
        'code_of_account' => '12902',
        'description' => 'Rekonsiliasi H2H '.$this->unit->name,
        'credit' => null,
        'debit' => $sum,
        'units_id' => 95,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);
    }

    public function logToJournal() {
      $mt940 = collect(DB::select(DB::raw('
        SELECT *, SUM(nominal) as total
        FROM
          mt940,
          ( 
            SELECT distinct unit_id, va_code, name, unit_code from api_kliksekolah.prm_va
            INNER JOIN api_kliksekolah.prm_school_units on prm_school_units.id = prm_va.unit_id
          ) b
        WHERE payment_date = "'. $this->date . '"
        GROUP BY unit_id
      ')));

      foreach($mt940 as $data) {
        $timestamp = Carbon::now();

        $journalNumber = $this->generateJournalNumber($this->date, 95);
        $this->logJournal([
          'journal_id' => 0,
        'journal_number' => $journalNumber,
        'date' => $this->date,
        'code_of_account' => '11310',
        'description' => 'Rekonsiliasi H2H '.$data->name,
        'credit' => $data['total'],
        'debit' => null,
        'units_id' => 95,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
        ]);

        $journalNumber = $this->generateJournalNumber($this->date, $data['unit_id']);
        $this->logJournal([
        'journal_id' => 0,
        'journal_number' => $journalNumber,
        'date' => $this->date,
        'code_of_account' => '12902',
        'description' => 'Rekonsiliasi H2H '.$data->name,
        'credit' => null,
        'debit' => $data['total'],
        'units_id' => $data['unit_id'],
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);
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
          mt940 on mt940.id = tr_invoices.mt940_id
        INNER JOIN api_kliksekolah.prm_va ON prm_va.va_code = mt940.va_code
        INNER JOIN api_kliksekolah.prm_payments ON prm_payments.id = tr_payment_details.payments_id
        WHERE mt940.payment_date = "' . $this->date . '"
        GROUP BY prm_va.unit_id, payments_id
      '));

      foreach($result as $data) {
        $timestamp = Carbon::now();
        $journalNumber = $this->generateJournalNumber($this->date, $data['unit_id']);
        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $this->date,
          'code_of_account' => $data['coa'],
          'description' => $data['name'],
          'credit' => null,
          'debit' => $data['total'],
          'units_id' => $data['unit_id'],
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
          mt940 on mt940.id = tr_invoices.mt940_id
        INNER JOIN 
          api_kliksekolah.prm_va ON prm_va.va_code = mt940.va_code
        INNER JOIN 
          api_kliksekolah.prm_payments ON prm_payments.id = tr_payment_discounts.payments_id
        WHERE mt940.payment_date = "' . $this->date . '"
        GROUP BY prm_va.unit_id, payments_id
      '));

      foreach($result as $data) {
        $timestamp = Carbon::now();
        $journalNumber = $this->generateJournalNumber($this->date, $data['unit_id']);
        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $this->date,
          'code_of_account' => $data['coa'],
          'description' => $data['name'],
          'credit' => null,
          'debit' => $data['total'],
          'units_id' => $data['unit_id'],
          'countable' => 1,
          'created_at' => $timestamp,
          'updated_at' => $timestamp
        ]);
      }
    }
    public function handleMT940ForcedOK() {
      try {
      	$mt940 = DB::table('mt940')
      	->select('id', 'nominal', 'va')
      	->where('payment_date', $this->date)
      	->get(); 
      	echo("BEGIN ==== ".$this->date."\n");
				$ids = $mt940->pluck('id');
		
        $trInvoices = DB::table('tr_invoices')
        ->select(DB::raw('SUM(nominal) as total_inv, mt940_id'))
        ->whereIn('mt940_id', $ids)
        ->groupBy('mt940_id')
        ->get();
		
		    $total = 0;
		      	
      	foreach($mt940 as $row) {
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
            'description' => 'Lebih bayar H2H ',
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
          	'description' => 'Lebih bayar H2H',
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
}
