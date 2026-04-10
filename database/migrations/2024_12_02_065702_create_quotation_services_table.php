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
        Schema::create('quotation_services', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('quotation_id')->unsigned()->index();
            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('service_id')->unsigned()->index();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade')->onUpdate('cascade');

            $table->float('service_price')->nullable();
            $table->float('discount')->nullable();
            $table->integer('qty')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_services');
    }
};
