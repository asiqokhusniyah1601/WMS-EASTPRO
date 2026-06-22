<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alter warehouses table
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type')->default('CABANG')->after('name'); // PUSAT, REGIONAL, CABANG
        });

        // 2. Create customers table
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('contract_no')->nullable();
            $table->timestamps();
        });

        // 3. Create customer_devices table
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('device_id');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });

        // 4. Create device_inspections table
        Schema::create('device_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('condition'); // GOOD, DAMAGED, UNKNOWN
            $table->text('notes')->nullable();
            $table->string('qc_result'); // PASSED, FAILED
            $table->string('operator');
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });

        // 5. Create accessory_transactions table
        Schema::create('accessory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('accessory_code');
            $table->integer('qty');
            $table->string('action'); // OUT, RETURN
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('technician_code')->nullable();
            $table->timestamps();

            $table->foreign('accessory_code')->references('code')->on('accessories')->onDelete('cascade');
            // Can be null if it's issued to a customer, or we can just leave it as string
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessory_transactions');
        Schema::dropIfExists('device_inspections');
        Schema::dropIfExists('customer_devices');
        Schema::dropIfExists('customers');
        
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
