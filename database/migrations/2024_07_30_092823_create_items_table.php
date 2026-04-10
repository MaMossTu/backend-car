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
        Schema::create('items', function (Blueprint $table) {
            $table->increments('id');

            $table->string('QTY')->default(0);

            $table->string('image');

            $table->string('name');

            $table->float('price');

            $table->string('descriptions')->nullable();

            $table->integer('item_type_id')->unsigned()->index()->nullable();
            $table->foreign('item_type_id')->references('id')->on('item_types')->onDelete('cascade')->onUpdate('cascade');

            $table->integer('vendor_id')->unsigned()->index()->nullable();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade')->onUpdate('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
