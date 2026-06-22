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
        Schema::create('stock_alert_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code');
            $table->string('item_type'); // e.g. DEVICE, ACCESSORY, SIMCARD
            $table->string('item_identifier'); // e.g. "GPS Tracker", "Dashcam", "Kabel Ekstensi"
            $table->integer('min_stock_level')->default(0);
            $table->timestamps();

            // Ensure unique constraint per warehouse per item
            $table->unique(['warehouse_code', 'item_type', 'item_identifier'], 'wh_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_alert_thresholds');
    }
};
