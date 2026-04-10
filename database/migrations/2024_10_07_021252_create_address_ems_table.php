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
        Schema::create('address_ems', function (Blueprint $table) {
            $table->id();

            $table->integer('inspection_id')->unsigned()->index();
            $table->foreign('inspection_id')->references('id')->on('inspections')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('province_id')->unsigned()->index();
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('district_id')->unsigned()->index();
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('subdistrict_id')->unsigned()->index();
            $table->foreign('subdistrict_id')->references('id')->on('sub_districts')->onDelete('cascade')->onUpdate('cascade');

            $table->string('address');
            $table->string('name');
            $table->string('photo')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('address_ems');
    }
};
