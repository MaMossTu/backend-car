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
        Schema::create('payout_commissions', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('employee_id')->unsigned()->index()->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('work_day');
            $table->integer('absent_day');
            $table->date('paid_date');
            $table->float('paid_price');
            $table->float('commission_per_em');
            $table->integer('commission_per_day');
            $table->string('description',255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_commissions');
    }
};
