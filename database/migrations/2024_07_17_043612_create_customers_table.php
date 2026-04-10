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
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no')->index();
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('phone_number1')->index();
            $table->string('phone_number2')->nullable();
            $table->string('tax_id')->nullable();
            $table->enum('type',['personal','vendor','agent'])->default(value: 'personal')->comment('นามบุคคล บริษัท ตัวแทน');
            $table->boolean('is_headquarter')->nullable()->comment('สำนักงานใหญ่หรือไม่');
            // $table->boolean('is_backoffice')->nullable()->comment('ตัวแทนหลังร้านหรือไม่');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
