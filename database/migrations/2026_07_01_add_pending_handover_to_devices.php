<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // User ID penerima yang perlu konfirmasi serah terima (nullable = tidak pending)
            $table->unsignedBigInteger('pending_handover_to_user_id')->nullable()->after('warehouse_code');
            $table->foreign('pending_handover_to_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['pending_handover_to_user_id']);
            $table->dropColumn('pending_handover_to_user_id');
        });
    }
};
