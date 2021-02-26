<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;

class UpdateTrInvoicesTableJob extends Job
{
    private $records = [];
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($records)
    {
        $this->records = $records;
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      foreach($this->records as $record) {
        DB::beginTransaction();
        try {
          $siswa = DB::table('ms_temp_siswa')
          ->select('name')
          ->where('id', $record['temps_id'])
          ->first();

          $invoice = DB::table('tr_invoices')
          ->select('nominal', 'payments_date')
          ->where('id', $record['id'])
          ->where('periode', $record['periode'])
          ->where('temps_id', $record['temps_id'])
          ->where('academics_year', $record['academics_year'])
          ->first();

          $invoiceNominal = $invoice ? $invoice->nominal : 0;

          DB::table('tr_invoices_mt940')
          ->insert([
            'organizations_id' => 3,
            'users_id' => 0,
            'id' => $record['id'],
            'periode' => $record['periode'],
            'temps_id' => $record['temps_id'],
            'academics_year' => $record['academics_year'],
            'payments_date' => $record['payments_date'],
            'collectible_name' => $siswa ? $siswa->name : '',
            'nominal' => $record['nominal'],
            'diff' => $record['nominal'] != $invoiceNominal,
          ]);

          if ($invoice && $invoice->payments_date == null) {
            $affected = DB::table('tr_invoices')
            ->select('nominal', 'payments_date')
            ->where('id', $record['id'])
            ->where('periode', $record['periode'])
            ->where('temps_id', $record['temps_id'])
            ->where('academics_year', $record['academics_year'])
            ->update([
              'payments_date' => $record['payments_date']
            ]);
            if ($affected > 0) {
              DB::table('tr_invoice_details')
              ->insert([
                'invoices_id' => $record['id'],
                'receipts_id' => $record['id'],
                'nominal' => $record['nominal'],
                'payments_date' => $record['payments_date'],
                'payments_type' => 'H2H',
                'nobukti' => $record['id'],
              ]);
            }
          }
          DB::commit();
        } catch (Exception $e) {
          DB::rollback();
          throw $e;
        }
      }
    }
}
