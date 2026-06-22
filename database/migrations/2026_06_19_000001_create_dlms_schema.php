<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('accessories', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->integer('qty')->default(0);
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('imei')->unique();
            $table->string('type');
            $table->string('model');
            $table->string('status');
            $table->string('current_holder');
            $table->string('warehouse_code');
            $table->timestamps();

            // Indexes for fast lookup (< 2 Seconds target)
            $table->index('serial_number');
            $table->index('imei');
            $table->index('status');
            
            $table->foreign('warehouse_code')->references('code')->on('warehouses')->onDelete('cascade');
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->string('id')->primary(); // DO number / Surat Jalan e.g. SJ-260619-01
            $table->string('from_warehouse_code');
            $table->string('to_warehouse_code');
            $table->string('status'); // IN_TRANSIT, RECEIVED
            $table->timestamps();

            $table->foreign('from_warehouse_code')->references('code')->on('warehouses')->onDelete('cascade');
            $table->foreign('to_warehouse_code')->references('code')->on('warehouses')->onDelete('cascade');
        });

        Schema::create('delivery_order_devices', function (Blueprint $table) {
            $table->string('delivery_order_id');
            $table->unsignedBigInteger('device_id');
            $table->primary(['delivery_order_id', 'device_id']);

            $table->foreign('delivery_order_id')->references('id')->on('delivery_orders')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });

        Schema::create('device_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('device_sn');
            $table->string('action'); // RECEIVING, TRANSFER_OUT, TRANSFER_IN, ISSUED
            $table->string('from_location');
            $table->string('to_location');
            $table->string('operator');
            $table->string('scanned_by');
            $table->boolean('via_web')->default(true);
            $table->timestamps();

            $table->index('device_id');
            $table->index('device_sn');
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_transactions');
        Schema::dropIfExists('delivery_order_devices');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('accessories');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('warehouses');
    }
};
