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
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      DB::transaction(function() {
        try {
          $data = $this->getReconciliatedData();
          foreach($data as $payment) {
            $unitId = $payment['units_id'];
            $date = date('Y-m-d', strtotime('2022-04-14'));
            $transactions = collect($payment['transactions']);
            foreach($transactions as $date => $items) {
              $this->createTransaction($unitId, $date, $items);
              $this->createReconciliation($unitId, $date, $items);
            }
          }
        } catch (Exception $e) {
          throw $e;
        }
      });
    }


    public function getReconciliatedData() {
      $unitId = $this->unitId;

      $data = [];

      $unitsVA = DB::table('prm_va')
        ->where(function($q) use ($unitId) {
          if(isset($unitId) && !empty($unitId)) {
            $q->where('unit_id', $unitId);
          }
        })->get();

      foreach($unitsVA as $va) {
        $transactions = DB::table('daily_reconciled_reports')
        ->whereRaw('DATE(daily_reconciled_reports.created_at) = ?', $this->date)
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

    public function generateJournalNumber($date, $unitId) {
      $month = date('m', strtotime($date));
      $year = date('Y', strtotime($date));
      $shortYear = date('y', strtotime($date));
      $unit = DB::connection('finance_db')->table('prm_school_units')->where('id', $unitId)->first();

      $counter = DB::connection('finance_db')->table('journal_logs')
      ->select('id')
      ->whereRaw('MONTH(journal_date) = ?', $date)
      ->whereRaw('YEAR(journal_date) = ?', $date)
      ->whereRaw('units_id = ?', $unitId)
      ->distinct()
      ->get();

    	$journalNumber = 'H2H';
    	$journalNumber = $journalNumber . $shortYear . str_pad($month, 2, '0', STR_PAD_LEFT) . '001' . $unit->unit_code;

      return $journalNumber;
    }

    private function logJournal($data) {
      foreach($details as $detail) {
        DB::connection('finance_db')->table('journal_logs')->insert([
          'journals_id' => $data['journal_id'],
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
      //Units
      // 12902
      //      coa
      $sum = $items->sum('nominal');
      $timestamp = Carbon::now();
      $journalNumber = $this->generateJournalNumber($date, $unitId);
      $this->logJournal([
        'journal_id' => null,
        'journal_number' => $journal_number,
        'date' => $date,
        'code_of_account' => '12902',
        'description' => 'Rekonsiliasi H2H',
        'debit' => $sum,
        'credit' => null,
        'units_id' => $unitId,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);

      foreach($items as $item) {
        $this->logJournal([
          'journal_id' => null,
          'journal_number' => $journal_number,
          'date' => $date,
          'code_of_account' => $item->code_of_account,
          'description' => 'Rekonsiliasi H2H',
          'debit' => null,
          'credit' => $item->nominal,
          'units_id' => $unitId,
          'countable' => 1,
          'created_at' => $timestamp,
          'updated_at' => $timestamp
        ]);
      }

      $journalNumber = $this->generateJournalNumber($date, 95);
      $this->logJournal([
        'journal_id' => null,
        'journal_number' => $journal_number,
        'date' => $date,
        'code_of_account' => '11310',
        'description' => 'Rekonsiliasi H2H',
        'debit' => $sum,
        'credit' => null,
        'units_id' => $unitId,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);
      $this->logJournal([
        'journal_id' => null,
        'journal_number' => $journal_number,
        'date' => $date,
        'code_of_account' => '12902',
        'description' => 'Rekonsiliasi H2H',
        'debit' => null,
        'credit' => $sum,
        'units_id' => $unitId,
        'countable' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp
      ]);

    }
}
