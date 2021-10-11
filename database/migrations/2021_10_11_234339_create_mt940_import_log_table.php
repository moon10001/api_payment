<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMt940ImportLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mt940_import_log', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->default('');
            $table->date('processed_at')->nullable();
            $table->string('status', 50)->nullable();
            $table->longText('error_log'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mt940_import_log');
    }
}
