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
        Schema::table('stock_opname_sessions', function (Blueprint $table) {
            $table->date('opname_date')->nullable()->after('warehouse_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_sessions', function (Blueprint $table) {
            $table->dropColumn('opname_date');
        });
    }
};
