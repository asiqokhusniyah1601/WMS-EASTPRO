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
        Schema::create('simcard_masters', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique()->comment('Kode master identifikasi tipe kartu SIM');
            $table->string('provider')->comment('Provider telekomunikasi (Telkomsel, XL, dll.)');
            $table->string('category')->nullable()->comment('Kategori kartu SIM (Halo, B2B, dll.)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simcard_masters');
    }
};
