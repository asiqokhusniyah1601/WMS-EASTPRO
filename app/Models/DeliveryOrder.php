<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliveryOrder extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'from_warehouse_code', 'to_warehouse_code', 'status'];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_code', 'code');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_code', 'code');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'delivery_order_devices', 'delivery_order_id', 'device_id');
    }

    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Accessory::class, 'delivery_order_accessories', 'delivery_order_id', 'accessory_code', 'id', 'code')
            ->withPivot('qty');
    }

    public function simcards(): BelongsToMany
    {
        return $this->belongsToMany(GsmSimcard::class, 'delivery_order_simcards', 'delivery_order_id', 'gsm_simcard_id');
    }
}
