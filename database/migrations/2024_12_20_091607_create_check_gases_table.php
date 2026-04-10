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
        Schema::create('check_gases', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('inspection_vehicle_id')->unsigned()->index()->nullable()->comment('รถที่ตรวจ');
            $table->foreign('inspection_vehicle_id')->references('id')->on('inspection_vehicles')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('engineer_id')->unsigned()->index()->nullable()->comment('พนักงานที่เป็นวิศวะกร');
            $table->foreign('engineer_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('customer_id')->unsigned()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade')->onUpdate('cascade');

            $table->string('customer_no');
            $table->string('customer_name');

            $table->string('engineer_no')->nullable()->comment('เลขที่วิศวกร');
            $table->string('engineer_name')->nullable()->comment('ชื่อวิศวกร');

            $table->string('service_or')->nullable()->comment('เลขที่ตรวจในใบออกแก๊ส');
            $table->string('install_no')->nullable()->comment('เลขใบติดตั้ง');
            // $table->string('CHA_NO')->nullable()->comment('เลขต่อถัง');

            $table->datetime('date_time')->nullable()->comment('วันเวลา');

            $table->string('photo_vehicle_gas')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_gases');
    }
};
