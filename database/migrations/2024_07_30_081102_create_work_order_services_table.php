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
        Schema::create('work_order_services', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('work_order_id')->unsigned()->index();
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade')->onUpdate('cascade');

            // $table->integer('repair_service_id')->unsigned()->index();
            // $table->foreign('repair_service_id')->references('id')->on('repair_services')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('service_id')->unsigned()->index();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('qty')->nullable();
            $table->string('service_name');
            $table->float('service_price')->nullable();
            $table->float('unit_price')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_services');
    }
};
