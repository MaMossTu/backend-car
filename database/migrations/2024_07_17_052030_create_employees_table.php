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
        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->index();
            $table->string('last_name')->index();
            $table->string('email');
            $table->date('hire_day')->nullable();
            $table->string('phone_number');
            $table->string('code')->nullable()->comment('เลขกว.');
            $table->enum('type',['front','back'])->default('back')->comment('พนง หน้าร้าน พนงซ่อมหลังร้าน');
            $table->boolean('is_engineer')->default(0)->comment('เป็นวิศวะกรหรือไม่');

            $table->integer('branch_id')->unsigned()->index();
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade')->onUpdate('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
