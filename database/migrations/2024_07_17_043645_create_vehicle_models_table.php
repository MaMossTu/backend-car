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
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vehicle_brand_id')->unsigned()->index();
            $table->foreign('vehicle_brand_id')->references('id')->on('vehicle_brands')->onDelete('cascade')->onUpdate('cascade');

            $table->string('name')->charset('utf8')->index();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
