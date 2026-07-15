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
        Schema::table('handover_receipts', function (Blueprint $table) {
            $table->string('issuer_name')->nullable()->after('target_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handover_receipts', function (Blueprint $table) {
            $table->dropColumn('issuer_name');
        });
    }
};
