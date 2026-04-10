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
        Schema::create('vechicle_services_transactions', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('inspection_vehicle_id')->unsigned();
            $table->foreign('inspection_vehicle_id')->references('id')->on('inspection_vehicles')->onDelete('cascade');

            $table->integer('service_id')->unsigned()->index();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->float('service_name')->nullable();
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
        Schema::dropIfExists('vechicle_services_transactions');
    }
};
