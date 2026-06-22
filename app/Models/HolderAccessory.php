<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolderAccessory extends Model
{
    public const TYPE_TECHNICIAN = 'TECHNICIAN';
    public const TYPE_CUSTOMER   = 'CUSTOMER';

    protected $fillable = [
        'holder_type',
        'holder_code',
        'holder_name',
        'accessory_code',
        'qty',
    ];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class, 'accessory_code', 'code');
    }
}
