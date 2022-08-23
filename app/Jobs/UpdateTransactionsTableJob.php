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
        var_dump($date);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      try {
        $data = $this->getReconciliatedData();
        foreach($data as $unitId => $payment) {
          $unitId = $unitId;
          $transactions = collect($payment);
          $date = $this->date;
          $this->createReconciliation($unitId, $date, $transactions);
        }
      } catch (Exception $e) {
        throw $e;
      }
    }


    public function getReconciliatedData() {
      $unitId = $this->unitId;

      $data = [];

      DB::enableQueryLog();
      $transactions = DB::table('daily_reconciled_reports')
        ->join('prm_payments', 'prm_payments.id', 'daily_reconciled_reports.prm_payments_id')
        ->whereRaw('DATE(daily_reconciled_reports.payment_date) = ?', $this->date)
        ->orderBy('units_id', 'ASC')
        ->get();

	  var_dump(['transactions' => $transactions->count()]);
      if ($transactions->isNotEmpty()) {
        $data = $transactions->mapToGroups(function ($item, $key) {
          return [$item->units_id => $item];
        });
      }
      var_dump(['grouped' => count($data)]);

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

      foreach($items as $item) {
        $this->logJournal([
          'journal_id' => 0,
          'journal_number' => $journalNumber,
          'date' => $date,
          'code_of_account' => $this->paymentCoa[$item->name],
          'description' => $item->name,
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
          'description' => $item->name,
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
        'description' => 'Rekonsiliasi H2H',
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
        'description' => 'Rekonsiliasi H2H',
        'credit' => null,
        'debit' => $sum,
        'units_id' => 95,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);

    }
}
