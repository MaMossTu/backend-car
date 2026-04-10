<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('insurance_types', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vehicle_inspection_types_id')->unsigned()->index();
            $table->foreign('vehicle_inspection_types_id')->references('id')->on('vehicle_inspection_types')->onDelete('cascade');

            $table->string('code')->charset('utf8')->index();
            $table->string('name')->charset('utf8')->index();
            $table->float('price')->nullable();
            $table->float('stamp')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_types');
    }
};
