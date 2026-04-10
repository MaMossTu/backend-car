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
        Schema::create('branches', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->index();
            $table->string('business_name');
            $table->string('phone')->index();
            $table->string('license_number')->index();
            $table->string('logo')->nullable();
            $table->string('email');
            $table->enum('cal_com',['all','only'])->default('all');

            $table->string('open_close_time')->nullable()->comment('เวลาทำการ');

            $table->string('open_date')->nullable()->comment('วันทำการ');

            $table->string('tax_id')->nullable()->comment('ภาษีบริษัท');

            $table->integer('province_id')->unsigned()->index();
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('district_id')->unsigned()->index();
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('subdistrict_id')->unsigned()->index();
            $table->foreign('subdistrict_id')->references('id')->on('sub_districts')->onDelete('cascade')->onUpdate('cascade');

            $table->boolean('is_headquarter')->nullable()->comment('สำนักงานใหญ่หรือไม่');
            $table->integer('postal_code');

            $table->string('address');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
