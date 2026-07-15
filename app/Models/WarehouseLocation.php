<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseLocation extends Model
{
    protected $fillable = ['warehouse_code', 'rack_code', 'row_code', 'barcode', 'description'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }

    /**
     * Parse barcode lokasi format RAK-XX-ROW-XX menjadi komponen rack & row.
     * Mengembalikan ['rack' => 'RAK-01', 'row' => 'ROW-01'] atau null jika tidak cocok.
     */
    public static function parseBarcode(string $barcode): ?array
    {
        // Pattern: sesuatu-ROW-XX (misalnya RAK-01-ROW-01)
        if (preg_match('/^(.+)-(ROW-[^-]+)$/i', $barcode, $m)) {
            return ['rack' => $m[1], 'row' => strtoupper($m[2])];
        }
        return null;
    }
}
