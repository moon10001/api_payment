<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoaColumnsToDailyReconciledReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('daily_reconciled_reports', function (Blueprint $table) {
            $table->string('coa',25)->default('');
            $table->string('description', 255)->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('daily_reconciled_reports', function (Blueprint $table) {
            //
        });
    }
}
