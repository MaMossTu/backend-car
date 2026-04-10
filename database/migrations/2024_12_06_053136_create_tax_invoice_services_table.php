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
        Schema::create('tax_invoice_services', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('tax_invoice_id')->unsigned()->index();
            $table->foreign('tax_invoice_id')->references('id')->on('tax_invoices')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('service_id')->unsigned()->index();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade')->onUpdate('cascade');

            $table->string('service_name');
            $table->integer('qty')->nullable();
            $table->float('unit_price')->nullable();
            $table->float('discount')->nullable();
            $table->float('service_price')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_invoice_services');
    }
};
