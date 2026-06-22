<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseAccessory extends Model
{
    protected $fillable = ['warehouse_code', 'accessory_code', 'qty'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class, 'accessory_code', 'code');
    }
}
