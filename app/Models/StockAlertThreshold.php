<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlertThreshold extends Model
{
    protected $fillable = [
        'warehouse_code',
        'item_type',
        'item_identifier',
        'min_stock_level',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }
}
