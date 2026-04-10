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
        Schema::create('insurance_names', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name')->index()->nullable()->comment('ชื่อบริษัทประกัน');

            $table->string('ins_code')->index()->nullable()->comment('รหัสบริษัทประกัน');

            $table->string('logo')->index()->nullable()->comment('โลโก้');

            $table->boolean('is_use')->default(1)->comment('เปิดใช้งาน?');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_names');
    }
};
