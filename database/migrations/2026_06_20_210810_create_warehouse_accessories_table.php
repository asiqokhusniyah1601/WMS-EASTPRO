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
        Schema::create('warehouse_accessories', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code');
            $table->string('accessory_code');
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->foreign('warehouse_code')->references('code')->on('warehouses')->onDelete('cascade');
            $table->foreign('accessory_code')->references('code')->on('accessories')->onDelete('cascade');
            $table->unique(['warehouse_code', 'accessory_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_accessories');
    }
};
