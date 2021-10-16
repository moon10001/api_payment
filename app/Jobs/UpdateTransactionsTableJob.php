<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateTransactionsTableJob extends Job
{
    private $from;
    private $to;
    private $unitId;
    private $bankCoa = '11201';
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
    public function __construct($from = '', $to = '', $unitId = '')
    {
        $this->from = $from;
        $this->to = $to;
        $this->unitId = $unitId;
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
            $transactions = collect($payment['transactions']);
            foreach($transactions as $date => $items) {
              $this->createTransaction($unitId, $date, $items);
              $this->createReconciliation($unitId, $date, $items);
              $this->createReconciliation($this->destinationUnit, $date, $items, true);
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
        ->whereRaw('DATE(daily_reconciled_reports.created_at) = ?', date('Y-m-d'))
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

    private function generateJournalNumber($date, $unitId, $isCredit = true) {
      $unit = DB::connection('finance_db')->table('prm_school_units')->where('id', $unitId)->first();
      $unitCode = $unit->unit_code;

      $journalNumber = DB::connection('finance_db')->table('journals')
      ->select(
        DB::raw('
          CONCAT("BBM21", LPAD(MONTH(NOW()), 2, 0), LPAD(COUNT(journals.id)+1, 3, 0), prm_school_units.unit_code ) as journal_number
        ')
      )
      ->join('prm_school_units', 'journals.prm_school_units_id', 'prm_school_units.id')
      ->whereRaw('MONTH(date) = ?', '=', $month)
      ->whereRaw('YEAR(date) = ?', '=', $year)
      ->where('journal_number', 'like', $isCredit ? 'BBM' : 'BBK')
      ->where('journals.prm_school_units_id', $unitId)
      ->get();

      var_dump($journal_number);
    }

    private function logJournal($journalId, $journal, $details) {
      foreach($details as $detail) {
        DB::connection('finance_db')->table('journal_logs')->insert([
          'journals_id' => $journalId,
          'journal_number' => $journal['journal_number'],
          'date' => $journal['date'],
          'code_of_account' => $detail['code_of_account'],
          'description' => $detail['description'],
          'debit' => $detail['credit'],
          'credit' => $detail['debit'],
          'units_id' => $journal['units_id'],
          'countable' => 1,
          'created_at' => Carbon::now(),
          'updated_at' => Carbon::now()
        ]);
      }
    }

    private function createTransaction($unitId, $date, $items) {
      $journal = [
        'journal_number' => $this->generateJournalNumber($date, $unitId),
        'prm_school_units_id' => $unitId,
        'journal_type' => 'BANK',
        'code_of_account' => $this->bankCoa,
        'form_type' => 1,
        'source' => 'BANK',
        'date' => $date,
        'is_posted' => 1,
        'is_credit' => true,
        'units_id' => $unitId,
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now()
      ];

      $journalId = DB::connection('finance_db')->table('journals')
      ->insertGetId($journal);

      $details = [];
      foreach($items as $item) {
        $coa = $this->paymentCoa[$item->name];
        array_push($details, [
          'journals_id' => $journalId,
          'code_of_account' => $this->bankCoa,
          'description' => $item->name,
          'journal_source' => 'BANK',
          'credit' => $item->nominal,
          'debit' => null,
        ]);
        array_push($details, [
          'journals_id' => $journalId,
          'description' => $item->name,
          'code_of_account' => $coa,
          'journal_source' => null,
          'credit' => null,
          'debit' => $item->nominal,
        ]);
      }
      DB::connection('finance_db')->table('journal_details')->insert($details);
      DB::connection('finance_db')->table('entity_units')->insert([
      	'entity_type' => 'App\Journals',
      	'entity_id' => $journalId,
      	'prm_school_units_id' => $unitId,
      	'created_at' => Carbon::now(),
      	'updated_at' => Carbon::now(),
      ]);


      $this->logJournal($journalId, $journal, $details);
    }

    private function createReconciliation($unitId, $date, $items, $isCredit = false) {
      $sum = $items->sum('nominal');
      $journal = [
        'journal_number' => $this->generateJournalNumber($date, $unitId, $isCredit),
        'destination_unit_id' => $isCredit ? $unitId : $this->destinationUnit,
        'units_id' => $unitId,
        'code_of_account' => $this->bankCoa,
        'date' => $date,
        'journal_type' => 'BANK',
        'source' => 'BANK',
        'form_type' => 2,
        'is_posted' => 1,
        'is_credit' => $isCredit,
        'prm_school_units_id' => $unitId,
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now()
      ];
      $journalId = DB::connection('finance_db')->table('journals')->insertGetId($journal);

      $details = [[
        'journals_id' => $journalId,
        'code_of_account' => $this->bankCoa,
        'description' => 'Rekonsiliasi Pembayaran',
        'credit' => $isCredit ? $sum : null,
        'journal_source' => 'BANK',
        'debit' => $isCredit ? null : $sum,
      ], [
        'journals_id' => $journalId,
        'code_of_account' => $this->reconciliationCoa,
        'description' => 'Rekonsiliasi Pembayaran',
        'credit' => $isCredit ? null : $sum,
        'journal_source' => null,
        'debit' => $isCredit ? $sum : null,
      ]];

      DB::connection('finance_db')->table('journal_details')->insert($details);
      $this->logJournal($journalId, $journal, $details);
      DB::connection('finance_db')->table('entity_units')->insert([
        'entity_type' => 'App\Journals',
        'entity_id' => $journalId,
        'prm_school_units_id' => $unitId,
        'created_at' =>	Carbon::now(),
        'updated_at' =>	Carbon::now(),
      ]);

    }
}
