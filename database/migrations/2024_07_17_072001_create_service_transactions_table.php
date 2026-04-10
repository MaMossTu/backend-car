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
        Schema::create('service_transactions', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('service_id')->unsigned()->index();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('inspections_id')->unsigned()->index();
            $table->foreign('inspections_id')->references('id')->on('inspections')->onDelete('cascade')->onUpdate('cascade');

            $table->float('service_price')->nullable();
            // $table->boolean('status')->default(1);

            // $table->timestamp('date_register')->nullable();
            // $table->enum('result',['pass','fail'])->nullable();
            // $table->string('or_check')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_transactions');
    }
};
