<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat saldo aksesoris yang dipegang non-gudang (teknisi & customer) sebagai
     * "balance" nyata, bukan sekadar disimpulkan dari log transaksi. Saldo awal
     * di-backfill dari accessory_transactions yang sudah ada agar histori tetap akurat.
     */
    public function up(): void
    {
        Schema::create('holder_accessories', function (Blueprint $table) {
            $table->id();
            $table->string('holder_type');        // TECHNICIAN | CUSTOMER
            $table->string('holder_code');        // technician.code | customer.id
            $table->string('holder_name')->nullable();
            $table->string('accessory_code');
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->unique(['holder_type', 'holder_code', 'accessory_code'], 'holder_acc_unique');
            $table->index(['holder_type', 'holder_code']);
        });

        $this->backfill();
    }

    /**
     * Hitung saldo awal dari log transaksi yang sudah ada.
     */
    private function backfill(): void
    {
        $now = now();
        $rows = [];

        // -------- TEKNISI (berdasarkan technician_code) --------
        $techNames = DB::table('technicians')->pluck('name', 'code');

        $techAgg = DB::table('accessory_transactions')
            ->whereNotNull('technician_code')
            ->selectRaw('technician_code, accessory_code, action, SUM(qty) as total')
            ->groupBy('technician_code', 'accessory_code', 'action')
            ->get();

        $techBalance = [];
        foreach ($techAgg as $r) {
            $key  = $r->technician_code . '|' . $r->accessory_code;
            $sign = in_array($r->action, ['RETURNED', 'RETURN', 'TRANSFER_IN'], true) ? -1 : 1;
            $techBalance[$key] = ($techBalance[$key] ?? 0) + $sign * (int) $r->total;
        }

        foreach ($techBalance as $key => $qty) {
            if ($qty <= 0) continue;
            [$code, $accCode] = explode('|', $key, 2);
            $rows[] = [
                'holder_type'    => 'TECHNICIAN',
                'holder_code'    => $code,
                'holder_name'    => $techNames[$code] ?? $code,
                'accessory_code' => $accCode,
                'qty'            => $qty,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // -------- CUSTOMER (berdasarkan prefix "Customer: " di to/from_location) --------
        $customerIdByName = DB::table('customers')->pluck('id', 'name');

        $custTx = DB::table('accessory_transactions')
            ->where('to_location', 'like', 'Customer: %')
            ->orWhere('from_location', 'like', 'Customer: %')
            ->get(['accessory_code', 'qty', 'to_location', 'from_location']);

        $custBalance = [];
        foreach ($custTx as $r) {
            if (str_starts_with((string) $r->to_location, 'Customer: ')) {
                $name = trim(substr($r->to_location, 10));
                $sign = 1;
            } elseif (str_starts_with((string) $r->from_location, 'Customer: ')) {
                $name = trim(substr($r->from_location, 10));
                $sign = -1;
            } else {
                continue;
            }
            $key = $name . '|' . $r->accessory_code;
            $custBalance[$key] = ($custBalance[$key] ?? 0) + $sign * (int) $r->qty;
        }

        foreach ($custBalance as $key => $qty) {
            if ($qty <= 0) continue;
            [$name, $accCode] = explode('|', $key, 2);
            $rows[] = [
                'holder_type'    => 'CUSTOMER',
                'holder_code'    => (string) ($customerIdByName[$name] ?? $name),
                'holder_name'    => $name,
                'accessory_code' => $accCode,
                'qty'            => $qty,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (!empty($rows)) {
            // Chunk untuk menghindari paket query yang terlalu besar.
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('holder_accessories')->insert($chunk);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holder_accessories');
    }
};
