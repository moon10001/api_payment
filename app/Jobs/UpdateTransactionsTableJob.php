<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            $unitId = $payment['unit_id'];
            $transactions = collect($payment['details']);
            foreach($transactions as $date => $items) {
              $this->createTransaction($unitId, $date, $items);
              $this->createReconciliation($unitId, $date, $items);
              $this->createReconciliation($unitId, $date, $items, false);
            }
          }
        } catch (Exception $e) {
          DB::Rollback();
          throw $e;
        }
      });
    }


    public function getReconciliatedData() {
      $from = $this->from;
      $to = $this->to;
      $unitId = $this->unitId;

      $data = [];

      $unitsVA = Cache::get('prm_va', function() use ($unitId) {
        $res = DB::table('prm_va')
        ->where(function($q) use ($unitId) {
          if(isset($unitId) && !empty($unitId)) {
            $q->where('unit_id', $unitId);
          }
        })->get();
        return $res;
      });

      foreach($unitsVA as $va) {
        $transactions = DB::table('daily_reconciled_reports')
        ->where(function($q) use ($from, $to) {
          if(isset($from) && !empty($from)) {
            $q->where('mt940.payment_date', '>=', $from);
          }
          if(isset($to) && !empty($to)) {
            $q->where('mt940.payment_date', '<=', $to);
          }
        })
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

      $month = date('m', strtotime($date));
      $year = date('y', strtotime($date));

      $counter = str_pad(
      strval(
      Journals::counter(
        'BANK',
        $isCredit,
        $month,
        date('Y', strtotime($date)),
        $unitId
      )->get()->count() + 1), 3,'0',STR_PAD_LEFT);

      $code = 'BB';
      if($isCredit) {
        $code = $code.'M';
      } else {
        $code = $code.'K';
      }

      $journalNumber = '$code'.$year.$month.str_pad($counter, 2, '0', STR_PAD_LEFT).$unitCode;
      return $journalNumber;
    }

    private function logJournal($journalId, $journal, $details) {
      foreach($details as $detail) {
        DB::connection('finance_db')->table('journal_logs')->insert([
          'journals_id' => $journalId,
          'journal_number' => $journal['journal_number'],
          'code_of_account' => $details->coa,
          'debit' => $detail['credit'],
          'credit' => $detail['debit'],
          'is_countable' => 1,
        ]);
      }
    }

    private function createTransaction($unitId, $date, $items) {
      $journal = [
        'journal_number' => $this->generateJournalNumber($date, $unitId),
        'units_id' => $unitId,
        'journal_type' => 'BANK',
        'code_of_account' => $this->bankCoa,
        'type' => 1,
        'source' => 'BANK',
        'date' => $date,
        'is_posted' => 1,
        'is_credit' => true,
      ];

      $journalId = DB::connection('finance_db')->table('journals')
      ->insertGetId($journal);

      $details = [[
        'journals_id' => $journalId,
        'code_of_account' => $this->bankCoa,
        'description' => $item['name'],
        'credit' => $transactions->sum('nominal'),
        'debit' => null,
      ]];
      foreach($items as $item) {
        $coa = $paymentCoa[$item['name']];
        array_push($details, [
          'journals_id' => $journalId,
          'description' => $item['name'],
          'code_of_account' => $coa,
          'credit' => null,
          'debit' => $item['nominal'],
        ]);
      }
      DB::connection('finance_db')->table('journal_details')->insert($details);
      $this->logJournal($journalId, $journal, $details);
    }

    private function createReconciliation($unitId, $date, $items, $isCredit = false) {
      $sum = $items->sum('nominal');
      $journal = [
        'journal_number' => $this->generateJournalNumber($date, $unitId, false),
        'units_id' => $isCredit ? $this->desctinationUnit : $unitId,
        'code_of_account' => $this->bankCoa,
        'date' => $date,
        'journal_type' => 'BANK',
        'source' => 'BANK',
        'form_type' => 2,
        'is_posted' => 1,
        'is_credit' => $isCredit,
        'prm_school_units_id' => $isCredit ? $unitId : $this->$destinationUnit,
      ];
      $journalId = DB::connection('finance_db')->table('journals')->insertGetId($journal);

      $details = [[
        'journals_id' => $journalId,
        'code_of_account' => $this->bankCoa,
        'credit' => $isCredit ? $sum : null,
        'debit' => $isCredit ? null : $sum,
      ], [
        'journals_id' => $journalId,
        'code_of_account' => $this->reconciliationCoa,
        'credit' => $isCredit ? null : $sum,
        'debit' => $isCredit ? $sum : null,
      ]];

      DB::connection('finance_db')->table('journal_details')->insert($details);
      $this->logJournal($journalId, $journal, $details);
    }
}
