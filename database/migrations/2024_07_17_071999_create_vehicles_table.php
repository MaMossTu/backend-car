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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('customer_id')->unsigned()->index()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade')->onUpdate('cascade');
            $table->string('file')->nullable();

            $table->date('registration_date')->index()->comment('วันที่จดทะเบียน');
            $table->string('license_plate')->unique()->index()->comment('เลขทะเบียน');

            $table->integer('province_id')->unsigned()->index()->comment('จังหวัด');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('vehicle_inspection_types_id')->unsigned()->index()->comment('ประเภท');
            $table->foreign('vehicle_inspection_types_id')->references('id')->on('vehicle_inspection_types')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('insurance_types_id')->unsigned()->index()->comment('ประเภท ย่อย');
            $table->foreign('insurance_types_id')->references('id')->on('insurance_types')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('brand_id')->unsigned()->index()->nullable()->comment('ยี่ห้อ');
            $table->foreign('brand_id')->references('id')->on('vehicle_brands')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('model_id')->unsigned()->index()->nullable()->comment('รุ่นรถ');
            $table->foreign('model_id')->references('id')->on('vehicle_models')->onDelete('cascade')->onUpdate('cascade');

            // $table->integer('insurance_names_id')->unsigned()->index()->nullable()->comment('บริษัทประกัน');
            // $table->foreign('insurance_names_id')->references('id')->on('insurance_names')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('chassis_number')->nullable()->comment('เลขตัวรถ');
            $table->string('engine_number')->nullable()->comment('เลขเครืองยนต์');
            $table->enum('fuel_type',['oil','gas','Hybrid'])->comment('เชื่้อเพลิง');
            $table->integer('cc')->comment('cc');
            $table->integer('weight')->comment('น้ำหนักรถ');
            $table->boolean('is_corporate')->nullable()->comment('ว่าเป็นนิติบุคคล');
            $table->integer('weight_loaded')->nullable()->comment('น้ำหนักบรรทุก');
            $table->integer('seat_count')->nullable()->comment('ที่นั่ง');

            $table->string('description')->nullable();
            $table->date('tax_renewal_date')->nullable()->comment('วันที่ยื่นภาษี');

            // $table->date('notificate_insurance')->nullable()->comment('วันครบกำหนดพรบ.');
            // $table->date('notificate_sub_insurance')->nullable()->comment('วันครบกำหนดประกัน');
            $table->date('notificate_tax')->nullable()->comment('วันครบกำหนดชำระภาษี');
            $table->enum('type_document',['real','copy'])->comment('เอกสาร สำเนา ใบจริง');
            $table->float('mileage')->nullable()->comment('เลขไมค์');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
