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
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('invoice_id')->unsigned()->index();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('billing_id')->unsigned()->index();
            $table->foreign('billing_id')->references('id')->on('billings')->onDelete('cascade')->onUpdate('cascade');

            $table->string('invoice_no');
            $table->string('invoice_date');
            $table->string('invoice_due_date');
            $table->string('invoice_total');
            $table->string('invoice_paid');
            $table->string('invoice_balance');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
