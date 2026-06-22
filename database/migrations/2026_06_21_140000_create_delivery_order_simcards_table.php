<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_order_simcards', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_order_id');
            $table->unsignedBigInteger('gsm_simcard_id');
            $table->timestamps();

            $table->unique(['delivery_order_id', 'gsm_simcard_id'], 'do_sim_unique');
            $table->index('gsm_simcard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_simcards');
    }
};
