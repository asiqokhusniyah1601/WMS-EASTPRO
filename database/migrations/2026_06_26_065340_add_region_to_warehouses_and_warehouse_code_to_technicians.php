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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('region')->nullable()->after('type')->comment('EAST, WEST');
        });

        Schema::table('technicians', function (Blueprint $table) {
            $table->string('warehouse_code')->nullable()->after('area');
            $table->foreign('warehouse_code')->references('code')->on('warehouses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->dropForeign(['warehouse_code']);
            $table->dropColumn('warehouse_code');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
