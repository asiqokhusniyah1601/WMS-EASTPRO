<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memberi kartu SIM dimensi gudang + log pergerakan, agar SIM bisa diterima,
     * dipindah, dan dimonitor stoknya per gudang seperti device & aksesoris.
     */
    public function up(): void
    {
        Schema::table('gsm_simcards', function (Blueprint $table) {
            if (!Schema::hasColumn('gsm_simcards', 'warehouse_code')) {
                $table->string('warehouse_code')->nullable()->after('status')->index();
            }
        });

        Schema::create('simcard_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gsm_simcard_id')->nullable();
            $table->string('msisdn');
            $table->string('action');            // RECEIVING | INSTALLED | RETURNED | TRANSFER_OUT | TRANSFER_IN | ADJUSTMENT
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('warehouse_code')->nullable();
            $table->string('operator')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('gsm_simcard_id')->references('id')->on('gsm_simcards')->onDelete('set null');
            $table->index(['warehouse_code', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simcard_transactions');

        Schema::table('gsm_simcards', function (Blueprint $table) {
            if (Schema::hasColumn('gsm_simcards', 'warehouse_code')) {
                $table->dropColumn('warehouse_code');
            }
        });
    }
};
