<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tambah tabel accessory_serial_numbers untuk mencatat SN individual aksesoris (opsional).
     */
    public function up(): void
    {
        Schema::create('accessory_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('accessory_code');
            $table->string('warehouse_code')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status')->default('IN_STOCK'); // IN_STOCK, ISSUED, etc.
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('accessory_code')->references('code')->on('accessories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessory_serial_numbers');
    }
};
