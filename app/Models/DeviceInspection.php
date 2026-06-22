<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceInspection extends Model
{
    protected $fillable = ['device_id', 'condition', 'notes', 'qc_result', 'operator'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
