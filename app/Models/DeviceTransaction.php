<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTransaction extends Model
{
    protected $fillable = [
        'device_id',
        'device_sn',
        'action',
        'from_location',
        'to_location',
        'operator',
        'scanned_by',
        'via_web',
        'notes'
    ];

    protected $casts = [
        'via_web' => 'boolean'
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
