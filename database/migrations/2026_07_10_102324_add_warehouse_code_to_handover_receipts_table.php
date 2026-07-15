<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handover_receipts', function (Blueprint $table) {
            $table->string('warehouse_code')->nullable()->after('issuer_name');
        });
    }

    public function down(): void
    {
        Schema::table('handover_receipts', function (Blueprint $table) {
            $table->dropColumn('warehouse_code');
        });
    }
};
