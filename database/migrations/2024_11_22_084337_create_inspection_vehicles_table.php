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
        Schema::create('inspection_vehicles', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('inspection_id')->unsigned()->index()->nullable();
            $table->foreign('inspection_id')->references('id')->on('inspections')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('vehicle_id')->unsigned()->index();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');
            // รถ
            $table->string('vehicle_inspection_types')->nullable();
            $table->string('insurance_types')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('provinces')->nullable();
            $table->date('registration_date')->nullable();
            $table->integer('cc')->comment('cc')->nullable();
            $table->integer('weight')->comment('น้ำหนักรถ')->nullable();
            $table->enum('type_document',['real','copy'])->nullable()->comment('เอกสาร สำเนา ใบจริง');
            $table->enum('fuel_type',['oil','gas','Hybrid'])->nullable()->comment('เชื่้อเพลิง');
            //ลูกค้า
            $table->string('no')->index()->nullable();
            $table->string('name')->index()->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->enum('type',['personal','vendor','agent'])->default(value: 'personal')->comment('นามบุคคล บริษัท ตัวแทน');
            $table->boolean('is_headquarter')->nullable()->comment('สำนักงานใหญ่หรือไม่');

            $table->text('remark')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_inspections');
    }
};
