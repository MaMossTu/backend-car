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
        Schema::create('line_items', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('work_order_id')->unsigned()->index()->nullable();
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('employee_id')->unsigned()->index();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('item_id')->unsigned()->index();
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('vendor_id')->unsigned()->index()->nullable();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade')->onUpdate('cascade');

            $table->date('date');
            $table->integer('stock')->comment('รับค่าเข้ามา withdraw , deposit??');
            $table->integer('qty')->comment('จำนวนใน item');
            $table->integer('balance')->comment('จำนวนหลังคำนวณแล้ว');
            $table->string('remark');
            $table->enum('operation',['booking','finish'])->nullable()->comment('สถานะของอะไหล่');
            $table->enum('type',['Deposit','Withdraw'])->nullable()->comment('เบิกหรือถอน');

            $table->boolean('status')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
