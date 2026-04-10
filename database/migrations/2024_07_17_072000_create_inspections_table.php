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
        Schema::create('inspections', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no')->index();

            // $table->integer('vehicle_inspection_types_id')->unsigned()->index();
            // $table->foreign('vehicle_inspection_types_id')->references('id')->on('vehicle_inspection_types')->onDelete('cascade')->onUpdate('cascade');

            // $table->integer('insurance_types_id')->unsigned()->index()->nullable();
            // $table->foreign('insurance_types_id')->references('id')->on('insurance_types')->onDelete('cascade')->onUpdate('cascade');

            // $table->integer('insurance_link_phone_id')->unsigned()->index()->nullable();
            // $table->foreign('insurance_link_phone_id')->references('id')->on('insurance_link_phones')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('branch_id')->unsigned()->index()->nullable();
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            $table->date('appointment')->comment('วันนัดรับบิล')->nullable();

            $table->enum('status',['open','overdue','unpaid','finish','cancel'])->default('open')->comment('สถานะใบวางบิล');

            $table->enum('tax_vehicle',['check','wait','send','complete','success'])->nullable()->comment('สถานะของภาษี');
            $table->enum('transaction_status',['adddebt','reducedebt','closedebt'])->nullable()->comment('เพิ่มหนี้ ลดหนี้ ปิดยอด');

            $table->integer('vehicle_id')->unsigned()->nullable();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('employee_id')->unsigned()->index()->comment('พนักงานที่ทำใบวางบิล');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('customer_id')->unsigned()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade')->onUpdate('cascade');

            $table->string('service_or')->nullable()->comment('เลขที่ตรวจในใบออกแก๊ส');
            $table->string('install_no')->nullable()->comment('เลขใบติดตั้ง');
            $table->string('CHA_NO')->nullable()->comment('เลขต่อถัง');

            $table->integer('engineer_id')->unsigned()->index()->nullable()->comment('พนักงานที่เป็นวิศวะกร');
            $table->foreign('engineer_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');

            $table->string('photo_vehicle_gas')->nullable();
            $table->integer('validprice')->nullable()->comment('ยืนราคา xx วัน');
            $table->string('EMS')->nullable();
            $table->date('date')->nullable();
            $table->string('result')->nullable();
            $table->string('remark')->nullable();
            $table->float('total')->nullable();
            $table->float('discount')->nullable();
            $table->float('paytrue')->nullable()->comment('จ่ายจริง');


            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
