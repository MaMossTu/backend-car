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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no');
            $table->text('remark')->nullable()->comment('หมายเหตุ อาการต่างๆ');
            $table->float('total');
            $table->date('date');

            $table->integer('vehicle_id')->unsigned()->index();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('branches_id')->unsigned()->index();
            $table->foreign('branches_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            $table->enum('status',['open','wait','repairing','success'])->default('open')->comment('สถานะ เปิด , รอซ่อม , กำลังซ่อม , เสร็จสิ้น');
            $table->enum('status_paid',['paid_cash','paid_tran','credit'])->default('paid_cash');
            // $table->enum('status_paid',['open','paid_cash','paid_tran','overdue','overdue(success)','credit','cancel'])->default('open');

            $table->integer('customer_id')->unsigned()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade')->onUpdate('cascade');

            $table->string('customer_no');
            $table->string('customer_name');
            $table->string('customer_address');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('customer_tax_id')->nullable();
            $table->boolean('customer_is_headquarter')->nullable()->comment('สำนักงานใหญ่หรือไม่');

            $table->string('brand_name')->nullable();
            $table->string('model_name')->nullable();
            $table->string('license_plate');
            $table->string('mileage')->nullable()->comment('เลขไมค์');
            $table->integer('chassis_number')->nullable()->comment('เลขตัวรถ');
            $table->string('engine_number')->nullable()->comment('เลขเครืองยนต์');


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
