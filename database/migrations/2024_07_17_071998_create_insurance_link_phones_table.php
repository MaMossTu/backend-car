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
        Schema::create('insurance_link_phones', function (Blueprint $table) {
            $table->increments('id');

            $table->string('phone_num')->index()->nullable()->comment('เบอร์ติดต่อตัวแทน , จนท ');

            // $table->integer('branch_id')->unsigned()->index();
            // $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            // $table->integer('insurance_name_id')->unsigned()->index();
            // $table->foreign('insurance_name_id')->references('id')->on('insurance_names')->onDelete('cascade')->onUpdate('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_link_phones');
    }
};
