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
        Schema::create('doc_customers', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('docs_id')->unsigned()->comment('เอกสาร');
            $table->foreign('docs_id')->references('id')->on('docs')->onDelete('cascade');

            $table->integer('customer_id')->unsigned()->comment('รหัสลูกค้า (ตัวแทนหลังร้าน)');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->integer('total_qty')->default(0)->comment('จำนวนเอกสารในสต็อก');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_customers');
    }
};
