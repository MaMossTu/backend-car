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
        Schema::create('save_documents', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('inspection_id')->unsigned()->index();
            $table->foreign('inspection_id')->references('id')->on('inspections')->onDelete('cascade')->onUpdate('cascade');

            $table->string('name');

            $table->string('document');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('save_documents');
    }
};
