<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Jejak audit QC penerimaan. Gating alur memakai status PENDING_QC,
            // kolom ini menyimpan siapa/kapan/catatan hasil QC.
            $table->string('qc_by')->nullable()->after('unit_condition');
            $table->timestamp('qc_at')->nullable()->after('qc_by');
            $table->text('qc_notes')->nullable()->after('qc_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['qc_by', 'qc_at', 'qc_notes']);
        });
    }
};
