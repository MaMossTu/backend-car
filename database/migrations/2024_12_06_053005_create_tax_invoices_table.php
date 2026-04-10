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
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('branche_id')->unsigned()->index();
            $table->foreign('branche_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('customer_id')->unsigned()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade')->onUpdate('cascade');

            $table->string('customer_no');
            $table->string('customer_name');
            $table->string('customer_address');
            $table->string('customer_address2');
            $table->string('customer_phone');
            $table->string('customer_email');
            $table->string('customer_tax_id');
            $table->boolean('customer_is_headquarter')->nullable()->comment('สำนักงานใหญ่หรือไม่');

            $table->string('no');
            // $table->string('seles order no')->nullable()->comment('เลขที่ใบสั่งขาย');
            // $table->string('salsman')->nullable()->comment('พนักงานขาย');
            $table->date('date');
            $table->text('remark')->nullable();
            $table->float('total');
            $table->float('discount');
            $table->float('tax');
            $table->float('total_amount');
            $table->enum('status',['draft','sent','approved','rejected'])->default('draft');
            $table->enum('vat',['in','out'])->default('out')->comment('ภาษีนอกรายการกับในรายการ');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_invoices');
    }
};
