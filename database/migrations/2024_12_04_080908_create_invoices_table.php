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
        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('branche_id')->unsigned()->index();
            $table->foreign('branche_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('quotation_id')->unsigned()->index();
            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade')->onUpdate('cascade');

            $table->string('customer_no');
            $table->string('customer_name');
            $table->string('customer_address');
            $table->string('customer_address2');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('customer_tax_id');
            $table->boolean('customer_is_headquarter')->nullable()->comment('สำนักงานใหญ่หรือไม่');

            $table->string('no');
            $table->date('date')->comment('วันที่ออกใบแจ้งหนี้');
            $table->date('due_date')->comment('วันครบกำหนดชำระ');
            $table->text('remark')->nullable();
            $table->float('total');
            $table->float('discount');
            $table->float('tax');
            $table->float('total_amount');
            $table->enum('status', ['paid', 'unpaid','cancel','billing'])->default('unpaid');
            $table->enum('type',['vat','nonvat'])->default('vat');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
