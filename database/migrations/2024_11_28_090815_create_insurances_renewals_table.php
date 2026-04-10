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
        Schema::create('insurances_renewals', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vehicle_id')->unsigned()->index();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');

            // $table->integer('inspection_id')->unsigned()->nullable()->comment('บิล');
            // $table->foreign('inspection_id')->references('id')->on('inspections')->onDelete('cascade');
            $table->integer('vechicle_services_transaction_id')->unsigned()->nullable()->comment('ตามบริการ');
            $table->foreign('vechicle_services_transaction_id')->references('id')->on('vechicle_services_transactions')->onDelete('cascade');

            $table->integer('insurance_names_id')->unsigned()->index();
            $table->foreign('insurance_names_id')->references('id')->on('insurance_names')->onDelete('cascade');

            $table->integer('insurance_renewal_type_id')->unsigned()->index()->nullable();
            $table->foreign('insurance_renewal_type_id')->references('id')->on('insurance_renewal_types')->onDelete('cascade');

            // $table->enum('insurance_type', ['ป1', 'ป2', 'ป2+', 'ป3', 'ป3+', 'ป4'])->comment('ประเภทของประกันภัย');

            $table->date('date_start')->comment('วันที่เริ่มคุ้มครอง ประกัน/พ.ร.บ');
            $table->date('date_end')->comment('วันที่สิ้นสุดคุ้มครอง ประกัน/พ.ร.บ');
            $table->double('amount_paid')->nullable()->comment('เบี้ยประกัน/เบี้ยพรบ.');
            $table->double('stamp')->nullable()->comment('อากรแสตมป์');
            $table->double('tax')->nullable()->comment('ภาษี 7%');
            $table->string('policy_number')->unique()->nullable()->comment('หมายเลขกรมธรรม์');
            $table->enum('policy_status', ['active', 'expired', 'cancelled'])->default('active')->comment('สถานะกรมธรรม์');
            $table->text('remark')->nullable()->comment('หมายเหตุ');

            $table->enum('type',['prb','insurances'])->comment('ประเภท');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurances_renewals');
    }
};
