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
        Schema::create('quotations', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('branche_id')->unsigned()->index();
            $table->foreign('branche_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('customer_id')->unsigned()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade')->onUpdate('cascade');

            $table->string('no');
            $table->string('name');
            $table->enum('payment_term',['cash','credit','tran'])->default('cash');
            $table->date('date');
            $table->date('valid_until');

            $table->text('remark')->nullable();
            $table->float('total');
            $table->float('discount');
            $table->float('tax');
            $table->float('total_amount');
            $table->enum('status',['draft','sent','approved','create','rejected'])->default('draft');
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
        Schema::dropIfExists('quotations');
    }
};
