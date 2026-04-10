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
        Schema::create('doc_transactions', function (Blueprint $table) {
            $table->increments('id');

            // $table->integer('doc_customers_id')->unsigned()->comment('รหัสเอกสารจากตาราง doc_customers');
            // $table->foreign('doc_customers_id')->references('id')->on('doc_customers')->onDelete('cascade');

            $table->integer('doc_id')->unsigned()->comment('รหัสเอกสารจากตาราง doc');
            $table->foreign('doc_id')->references('id')->on('docs')->onDelete('cascade');

            $table->integer('customer_id')->unsigned()->comment('รหัสเอกสารจากตาราง customers');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->integer('inspection_id')->unsigned()->nullable()->comment('รหัสการตรวจสอบ');
            $table->foreign('inspection_id')->references('id')->on('inspections')->onDelete('cascade');

            $table->date('date')->comment('วันที่ทำธุรกรรม');
            $table->enum('transaction_type', ['Deposit', 'Withdraw'])->comment('ประเภทธุรกรรม');
            $table->integer('stock')->comment('ก่อนคำนวณ');
            $table->integer('quantity')->comment('จำนวนเอกสาร');
            $table->integer('balance')->comment('ยอดคงเหลือหลังธุรกรรม');
            $table->text('remark')->nullable()->comment('หมายเหตุ');


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_transactions');
    }
};
