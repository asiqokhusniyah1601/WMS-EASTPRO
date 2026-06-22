<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('device_transactions', 'notes')) {
                $table->text('notes')->nullable()->after('via_web');
            }
        });

        Schema::table('accessory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('accessory_transactions', 'notes')) {
                $table->text('notes')->nullable()->after('technician_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('device_transactions', 'notes')) {
                $table->dropColumn('notes');
            }
        });

        Schema::table('accessory_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('accessory_transactions', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
