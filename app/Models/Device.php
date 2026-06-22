<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'serial_number',
        'imei',
        'type',
        'model',
        'status',
        'unit_condition',
        'qc_by',
        'qc_at',
        'qc_notes',
        'current_holder',
        'warehouse_code',
        'gsm_simcard_id',
        'vehicle_plate'
    ];

    protected $casts = [
        'qc_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }

    public function gsmSimcard(): BelongsTo
    {
        return $this->belongsTo(GsmSimcard::class, 'gsm_simcard_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DeviceTransaction::class, 'device_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(DeviceInspection::class, 'device_id');
    }

    public function customerDevices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class, 'device_id');
    }
}
