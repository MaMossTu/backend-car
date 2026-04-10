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
        Schema::create('installments', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('inspection_id')->unsigned()->index()->nullable();
            $table->foreign('inspection_id')->references('id')->on('inspections')->onDelete('cascade')->onUpdate('cascade');
            
            $table->enum('paid_type',['paid_cash','paid_tran','credit'])->nullable()->comment('ประเภทการจ่ายเงิน');

            $table->float('paid_price');
            $table->string('description',255)->nullable()->comment('รอบงวดให้แอด');

            $table->float('before_price')->nullable();
            $table->float('after_price')->nullable();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
