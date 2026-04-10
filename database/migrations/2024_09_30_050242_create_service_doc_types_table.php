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
        Schema::create('service_doc_types', function (Blueprint $table) {
            $table->id();

            $table->integer('document_types_id')->unsigned()->comment('ประเภทเอกสาร');
            $table->foreign('document_types_id')->references('id')->on('document_types')->onDelete('cascade');

            $table->integer('services_id')->unsigned()->comment('บริการ');
            $table->foreign('services_id')->references('id')->on('services')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_doc_types');
    }
};
