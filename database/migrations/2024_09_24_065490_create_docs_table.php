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
        Schema::create('docs', function (Blueprint $table) {
            $table->increments('id');

            $table->string('no')->nullable()->comment('เลขที่เอกสาร');
            $table->string('name')->nullable()->comment('ชื่อเอกสาร');
            $table->integer('document_type_id')->unsigned()->nullable()->comment('ประเภทเอกสาร');
            $table->foreign('document_type_id')->references('id')->on('document_types')->onDelete('cascade');

            $table->integer('engineer_id')->unsigned()->nullable()->comment('รหัสวิศวกร');
            $table->foreign('engineer_id')->references('id')->on('employees')->onDelete('cascade');

            $table->integer('insurance_names_id')->unsigned()->nullable()->comment('บริษัทประกัน');
            $table->foreign('insurance_names_id')->references('id')->on('insurance_names')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docs');
    }
};
