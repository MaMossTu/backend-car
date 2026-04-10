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
        Schema::create('vehicle_gases', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vehicle_id')->unsigned()->index();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');

            $table->string('gas_type')->nullable()->comment('ชนิดถัง');
            $table->string('gas_position')->nullable()->comment('ตำแหน่งที่ติดตั้ง');
            $table->enum('gas_category',['LPG','NGV'])->comment('ประเภทถัง');
            $table->string('gas_brand')->nullable()->comment('ยี่ห้อของถัง');
            $table->string('gas_number')->nullable()->comment('เลขของถัง');
            $table->string('gas_weight')->nullable()->comment('มวลของตัวถัง');
            $table->string('gas_thick')->nullable()->comment('ความหนาของถัง');
            $table->float('gas_capacity')->nullable()->comment('ปริมาณความจุของถัง');
            $table->date('gas_create')->nullable()->comment('วันที่ผลิตถัง');
            $table->date('gas_expire')->nullable()->comment('วันหมดอายุถัง');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_gases');
    }
};
