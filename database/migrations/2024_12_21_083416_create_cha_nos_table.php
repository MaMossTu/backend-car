<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cha_nos', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('check_gas_id')->unsigned()->index()->nullable();
            $table->foreign('check_gas_id')->references('id')->on('check_gases')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('vehicle_gas_id')->unsigned()->index()->nullable();
            $table->foreign('vehicle_gas_id')->references('id')->on('vehicle_gases')->onDelete('cascade')->onUpdate('cascade');

            $table->string('cha_no')->index()->comment('เลขใบต่อถัง');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cha_nos');
    }
};
