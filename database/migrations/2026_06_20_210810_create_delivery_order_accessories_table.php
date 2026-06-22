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
        Schema::create('delivery_order_accessories', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_order_id');
            $table->string('accessory_code');
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->foreign('delivery_order_id')->references('id')->on('delivery_orders')->onDelete('cascade');
            $table->foreign('accessory_code')->references('code')->on('accessories')->onDelete('cascade');
            $table->unique(['delivery_order_id', 'accessory_code'], 'do_acc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_order_accessories');
    }
};
