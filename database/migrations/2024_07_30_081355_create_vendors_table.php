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
        Schema::create('vendors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->integer('province_id')->unsigned()->index()->nullable();
            $table->foreign('province_id')->references('id')->on('provinces');

            $table->integer('district_id')->unsigned()->index()->nullable();
            $table->foreign('district_id')->references('id')->on('districts');

            $table->integer('sub_district_id')->unsigned()->index()->nullable();
            $table->foreign('sub_district_id')->references('id')->on('sub_districts');

            $table->string('zip_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
