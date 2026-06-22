<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoryTransaction extends Model
{
    protected $fillable = ['accessory_code', 'qty', 'action', 'from_location', 'to_location', 'technician_code', 'notes'];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class, 'accessory_code', 'code');
    }
}
