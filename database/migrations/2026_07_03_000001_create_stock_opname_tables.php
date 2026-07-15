<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================================
        // 1. warehouse_locations — Menyimpan lokasi rak & row/BIN
        //    Barcode gabungan: RAK-01-ROW-01 (1 scan = 1 lokasi)
        // =============================================================
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code', 50);
            $table->string('rack_code', 50);       // misal: RAK-01
            $table->string('row_code', 50);         // misal: ROW-01 (BIN)
            $table->string('barcode', 100)->unique(); // misal: RAK-01-ROW-01
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->foreign('warehouse_code')
                  ->references('code')->on('warehouses')->onDelete('cascade');
            $table->index(['warehouse_code', 'rack_code'], 'whloc_wh_rack_idx');
            $table->index('barcode', 'whloc_barcode_idx');
        });

        // =============================================================
        // 2. stock_opname_sessions — Satu sesi opname per gudang
        // =============================================================
        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code', 50);
            $table->enum('status', ['open', 'completed'])->default('open');
            $table->unsignedBigInteger('started_by');
            $table->timestamp('completed_at')->nullable();
            $table->json('crosscheck_result')->nullable(); // Hasil crosscheck lengkap
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_code')
                  ->references('code')->on('warehouses')->onDelete('cascade');
            $table->foreign('started_by')
                  ->references('id')->on('users');
            $table->index(['warehouse_code', 'status'], 'opname_sess_wh_status_idx');
        });

        // =============================================================
        // 3. stock_opname_items — Setiap baris scan per sesi
        //    Mendukung 3 jenis barang: device, accessory, simcard
        // =============================================================
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->string('location_barcode', 100)->nullable(); // RAK-01-ROW-01
            $table->string('rack_code', 50)->nullable();
            $table->string('row_code', 50)->nullable();
            $table->enum('item_type', ['device', 'accessory', 'simcard']);
            $table->string('item_code', 150); // SN / accessory_code / msisdn
            $table->string('item_name', 255)->nullable(); // Nama cached saat scan
            $table->integer('qty_physical')->default(1);
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->timestamps();

            $table->foreign('session_id')
                  ->references('id')->on('stock_opname_sessions')->onDelete('cascade');
            $table->foreign('scanned_by')
                  ->references('id')->on('users')->nullOnDelete();
            $table->index(['session_id', 'item_type'], 'opname_item_sess_type_idx');
            $table->index(['session_id', 'item_code'], 'opname_item_sess_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opname_sessions');
        Schema::dropIfExists('warehouse_locations');
    }
};
