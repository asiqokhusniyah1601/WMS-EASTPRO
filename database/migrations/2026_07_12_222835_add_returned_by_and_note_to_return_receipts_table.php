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
        Schema::table('return_receipts', function (Blueprint $table) {
            $table->string('returned_by')->nullable()->after('warehouse_code');
            $table->text('internal_note')->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_receipts', function (Blueprint $table) {
            $table->dropColumn(['returned_by', 'internal_note']);
        });
    }
};

