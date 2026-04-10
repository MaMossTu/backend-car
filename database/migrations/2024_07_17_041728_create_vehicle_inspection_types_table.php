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
        Schema::create('vehicle_inspection_types', function (Blueprint $table) {
            $table->increments('id');

            $table->enum('type',['รย.','ขส.'])->comment('ประเภท รถยนต์หรือขนส่ง');
            $table->string('code')->index();
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
        Schema::dropIfExists('vehicle_inspection_types');
    }
};
