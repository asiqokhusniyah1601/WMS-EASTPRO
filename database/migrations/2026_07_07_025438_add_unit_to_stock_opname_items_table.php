<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            // Satuan ukuran barang, misal: "pcs", "meter", "cm", "pack isi 10", dll.
            $table->string('unit', 100)->nullable()->after('qty_physical');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
