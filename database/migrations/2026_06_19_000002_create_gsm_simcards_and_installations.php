<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsm_simcards', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->unique();
            $table->string('provider');
            $table->string('category')->nullable();
            $table->string('status')->default('IN_STOCK'); // IN_STOCK, INSTALLED, SUSPENDED
            $table->timestamps();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('gsm_simcard_id')->nullable()->after('warehouse_code');
            $table->string('vehicle_plate')->nullable()->after('gsm_simcard_id');

            $table->foreign('gsm_simcard_id')->references('id')->on('gsm_simcards')->onDelete('set null');
            $table->index('vehicle_plate');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['gsm_simcard_id']);
            $table->dropColumn(['gsm_simcard_id', 'vehicle_plate']);
        });

        Schema::dropIfExists('gsm_simcards');
    }
};
