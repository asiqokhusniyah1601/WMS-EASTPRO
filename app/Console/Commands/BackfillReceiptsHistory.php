<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReceiptsHistory extends Command
{
    protected $signature   = 'receipts:backfill';
    protected $description = 'Backfill handover_receipts and return_receipts dari data transaksi lama';

    public function handle(): int
    {
        $this->backfillHandover();
        $this->backfillReturn();
        return 0;
    }

    // =========================================================
    // HANDOVER — dari device_transactions (ISSUED / INSTALLED / PENDING_ACCEPTANCE)
    //            dan accessory_transactions, simcard_transactions
    //            yang memakai notes = 'TT-...'
    // =========================================================
    private function backfillHandover(): void
    {
        $this->info('=== Backfill handover_receipts ===');

        // Kumpulkan receipt_no unik dari semua sumber transaksi
        $deviceNotes = DB::table('device_transactions')
            ->whereIn('action', ['ISSUED', 'INSTALLED', 'PENDING_ACCEPTANCE'])
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->where('notes', 'like', 'TT-%')
            ->select('notes', 'operator', 'to_location', 'from_location', 'created_at')
            ->get()
            ->groupBy('notes');

        $accNotes = DB::table('accessory_transactions')
            ->whereNotNull('notes')
            ->where('notes', 'like', 'TT-%')
            ->select('notes', 'created_at')
            ->get()
            ->groupBy('notes');

        $simNotes = DB::table('simcard_transactions')
            ->whereIn('action', ['ISSUED', 'INSTALLED'])
            ->whereNotNull('notes')
            ->where('notes', 'like', 'TT-%')
            ->select('notes', 'created_at')
            ->get()
            ->groupBy('notes');

        // Gabungkan semua receipt_no unik
        $allReceiptNos = $deviceNotes->keys()
            ->merge($accNotes->keys())
            ->merge($simNotes->keys())
            ->unique();

        // Ambil yang sudah ada di handover_receipts agar tidak duplikat
        $existing = DB::table('handover_receipts')
            ->pluck('receipt_no')
            ->flip();

        $inserted = 0;
        foreach ($allReceiptNos as $receiptNo) {
            if (isset($existing[$receiptNo])) {
                $this->line("  SKIP (sudah ada): {$receiptNo}");
                continue;
            }

            // Cari metadata dari transaksi device (prioritas utama)
            $firstDevice = $deviceNotes->get($receiptNo)?->first();
            $createdAt   = $firstDevice?->created_at
                        ?? $accNotes->get($receiptNo)?->first()?->created_at
                        ?? $simNotes->get($receiptNo)?->first()?->created_at
                        ?? now();

            $operator = $firstDevice?->operator ?? 'System';

            // Tentukan target_type & target_name dari to_location
            $toLocation  = $firstDevice?->to_location ?? '';
            $targetType  = 'UNKNOWN';
            $targetName  = $toLocation;

            if (str_starts_with($toLocation, 'Technician:')) {
                $targetType = 'TECHNICIAN';
                $targetName = trim(substr($toLocation, strlen('Technician:')));
            } elseif (str_starts_with($toLocation, 'Customer:')) {
                $targetType = 'CUSTOMER';
                $targetName = trim(substr($toLocation, strlen('Customer:')));
            }

            DB::table('handover_receipts')->insert([
                'receipt_no'  => $receiptNo,
                'target_type' => $targetType,
                'target_name' => $targetName,
                'issuer_name' => $operator,
                'is_accepted' => false,
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);

            $this->info("  INSERT handover: {$receiptNo} | {$targetType}: {$targetName} | by {$operator}");
            $inserted++;
        }

        $this->info("Handover: {$inserted} baris dimasukkan dari " . $allReceiptNos->count() . " receipt unik.");
    }

    // =========================================================
    // RETURN — dari device_transactions (RETURNED) yang belum punya receipt_no
    //           Data lama: notes berisi alasan, bukan receipt_no
    //           → Kita kelompokkan per (operator + tanggal + alasan) sebagai 1 "sesi return"
    // =========================================================
    private function backfillReturn(): void
    {
        $this->info('');
        $this->info('=== Backfill return_receipts ===');

        // Ambil semua transaksi RETURNED
        $returnTx = DB::table('device_transactions')
            ->where('action', 'RETURNED')
            ->select('notes', 'operator', 'to_location', 'created_at')
            ->orderBy('created_at')
            ->get();

        if ($returnTx->isEmpty()) {
            $this->info('Tidak ada transaksi RETURNED ditemukan.');
            return;
        }

        // Pisahkan: yang sudah punya receipt_no 'RET-...' vs yang belum
        $hasReceiptNo = $returnTx->filter(fn($r) => str_starts_with($r->notes ?? '', 'RET-'));
        $noReceiptNo  = $returnTx->reject(fn($r) => str_starts_with($r->notes ?? '', 'RET-'));

        // Cek yang sudah punya receipt_no RET-... apakah sudah ada di return_receipts
        $existingRets = DB::table('return_receipts')->pluck('receipt_no')->flip();

        // Grup yang sudah ber-receipt_no
        foreach ($hasReceiptNo->groupBy('notes') as $rno => $group) {
            if (isset($existingRets[$rno])) continue;
            $first = $group->first();
            DB::table('return_receipts')->insert([
                'receipt_no'    => $rno,
                'returner_name' => $first->operator ?? 'System',
                'warehouse_code'=> $first->to_location ?? '-',
                'reason'        => null,
                'created_at'    => $first->created_at,
                'updated_at'    => $first->created_at,
            ]);
            $this->info("  INSERT return (existing RET): {$rno}");
        }

        // Untuk transaksi lama tanpa receipt_no: kelompokkan per operator+tanggal+alasan
        $grouped = $noReceiptNo->groupBy(function ($r) {
            $date   = substr($r->created_at, 0, 10); // YYYY-MM-DD
            $reason = $r->notes ?? '';
            $op     = $r->operator ?? 'System';
            return "{$op}|{$date}|{$reason}";
        });

        $insertedOld = 0;
        foreach ($grouped as $key => $group) {
            $first  = $group->first();
            $reason = $first->notes ?? null;
            $op     = $first->operator ?? 'System';
            $date   = substr($first->created_at, 0, 10);

            // Buat receipt_no sintetis untuk data lama
            $ts = str_replace(['-', ' ', ':'], '', $first->created_at);
            $hash = strtoupper(substr(md5($key), 0, 4));
            $rno = "RET-{$ts}-{$hash}";

            if (isset($existingRets[$rno])) continue;

            // Ambil warehouse dari to_location pada transaksi pertama
            $wh = $first->to_location ?? '-';
            // to_location untuk return biasanya "Warehouse WH-CODE" atau "WH-CODE"
            if (str_starts_with($wh, 'Warehouse ')) {
                $wh = trim(substr($wh, strlen('Warehouse ')));
            }

            DB::table('return_receipts')->insert([
                'receipt_no'    => $rno,
                'returner_name' => $op,
                'warehouse_code'=> $wh,
                'reason'        => $reason,
                'created_at'    => $first->created_at,
                'updated_at'    => $first->created_at,
            ]);

            // Update notes di device_transactions agar merujuk ke receipt_no baru
            DB::table('device_transactions')
                ->where('action', 'RETURNED')
                ->whereDate('created_at', $date)
                ->where('operator', $op)
                ->where('notes', $reason)
                ->update(['notes' => $rno]);

            $this->info("  INSERT return (lama): {$rno} | {$op} | {$reason} | {$group->count()} device");
            $insertedOld++;
        }

        $this->info("Return: {$insertedOld} receipt lama dibuat.");
    }
}
